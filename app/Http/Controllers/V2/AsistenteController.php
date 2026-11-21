<?php

namespace App\Http\Controllers\V2;

use App\Domain\Asistente;
use App\Http\Controllers\Controller;
use App\Services\DiagnosticoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Balance assistant: one chat turn over the session and its cached diagnosis, through the Claude Messages API.
 * The model may call `proponer_accion`; valid proposals are returned to the client, never executed here.
 */
class AsistenteController extends Controller
{
    /** Whole request, diagnosis included: each model call gets what is left. */
    public const PRESUPUESTO_SEGUNDOS = 60;
    /** Below this, another model call is not worth starting. */
    private const MIN_LLAMADA_SEGUNDOS = 5;
    private const MAX_ROUNDS = 3;
    private const MAX_TOKENS = 4096;
    private const NO_RESPONDIO = 'El asistente no respondió. Intenta nuevamente en unos segundos.';
    private const NOTA_TIEMPO = '(Se alcanzó el tiempo máximo de respuesta; la respuesta puede estar incompleta.)';

    public function responder(Request $request, int $sesion)
    {
        $deadline = Carbon::now()->addSeconds(self::PRESUPUESTO_SEGUNDOS);
        if (!config('services.anthropic.enabled')) {
            return response()->json(['message' => 'El asistente no está habilitado.'], 404);
        }
        $body = $request->validate([
            'mensajes' => 'required|array|min:1|max:20',
            'mensajes.*' => 'required|array:rol,texto',
            'mensajes.*.rol' => 'required|in:usuario,asistente',
            'mensajes.*.texto' => 'required|string|max:4000',
        ]);
        // The API needs a user turn first, and a trailing assistant turn would be a prefill (rejected by the model).
        if ($body['mensajes'][0]['rol'] !== 'usuario' || end($body['mensajes'])['rol'] !== 'usuario') {
            return response()->json(['message' => 'La conversación debe empezar y terminar con un mensaje del usuario.', 'errors' => ['mensajes' => ['La conversación debe empezar y terminar con un mensaje del usuario.']]], 422);
        }

        // One request at a time per user: a second tab or a double click must not double the spend.
        $lock = Cache::lock('asistente:user:'.$request->user()->id, self::PRESUPUESTO_SEGUNDOS + 10);
        if (!$lock->get()) {
            return response()->json(['message' => 'Ya tienes una consulta al asistente en curso. Espera a que termine.'], 429);
        }
        try {
            return $this->conversar($request, $sesion, $body['mensajes'], $deadline);
        } finally {
            $lock->release();
        }
    }

    private function conversar(Request $request, int $sesion, array $mensajes, Carbon $deadline)
    {
        $s = SesionesController::find($sesion);
        $dto = SesionesController::dto($s);
        $diag = DiagnosticoService::deSesion($s);
        $sugeridos = Asistente::sugeridos($dto, $diag);

        // The session data goes in the first user turn as a delimited block (untrusted: it comes from uploaded files),
        // not in `system`. Render order is tools → system → messages, so the breakpoint on it caches the tool, the prompt
        // and the data, which stay byte-identical across the turns of a conversation on one session version.
        $messages = array_map(fn ($m) => ['role' => $m['rol'] === 'usuario' ? 'user' : 'assistant', 'content' => $m['texto']], $mensajes);
        $messages[0]['content'] = [
            ['type' => 'text', 'text' => "<datos_sesion>\n".Asistente::contexto($dto, $diag)."\n</datos_sesion>", 'cache_control' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => $messages[0]['content']],
        ];
        $betas = array_values(array_filter(array_map('trim', explode(',', (string) config('services.anthropic.betas')))));
        $base = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => self::MAX_TOKENS,
            'output_config' => ['effort' => 'medium'],
            'tools' => [Asistente::TOOL],
            'system' => Asistente::SYSTEM,
        ];
        // Server-side refusal fallback (claude-api skill default for Opus 5.5) only exists behind its beta.
        if (collect($betas)->contains(fn ($b) => str_starts_with($b, 'server-side-fallback-'))) {
            $base['fallbacks'] = 'default';
        }

        $textos = [];
        $propuestas = [];
        $claves = [];
        $cortado = false;
        $uso = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0];
        for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
            $restante = Carbon::now()->diffInMilliseconds($deadline, false) / 1000;
            if ($restante < self::MIN_LLAMADA_SEGUNDOS) {
                if ($round === 1) {
                    $this->fail(504, 'El asistente no alcanzó a responder a tiempo. Intenta nuevamente.');
                }
                $cortado = true;
                break;
            }
            try {
                $res = $this->llamar($base + ['messages' => $messages], $restante, $betas);
            } catch (ConnectionException $e) {
                Log::error('Asistente unreachable', ['exception' => $e::class]);
                if ($round === 1) {
                    $this->fail(502, self::NO_RESPONDIO);
                }
                $cortado = true; // what the earlier rounds produced is still worth returning
                break;
            }
            foreach ($uso as $k => $v) {
                $uso[$k] = $v + (int) ($res['usage'][$k] ?? 0);
            }
            $content = is_array($res['content'] ?? null) ? $res['content'] : [];
            foreach ($content as $block) {
                if (($block['type'] ?? null) === 'text' && trim($block['text'] ?? '') !== '') {
                    $textos[] = trim($block['text']);
                }
            }
            if (($res['stop_reason'] ?? null) === 'refusal') {
                $textos = ['El asistente no puede responder esta consulta. Reformúlala en términos del balance.'];
                $propuestas = [];
                break;
            }
            $usos = array_values(array_filter($content, fn ($b) => ($b['type'] ?? null) === 'tool_use'));
            if (($res['stop_reason'] ?? null) !== 'tool_use' || !$usos) {
                break;
            }

            $results = [];
            foreach ($usos as $u) {
                [$p, $motivo] = ($u['name'] ?? null) === 'proponer_accion' ? Asistente::validarPropuesta($u['input'] ?? null, $dto, $sugeridos) : [null, 'herramienta desconocida'];
                $clave = $p ? "{$p['idx']}|{$p['variable']}|{$p['tipo']}" : null;
                if ($p && isset($claves[$clave])) {
                    [$p, $motivo] = [null, 'propuesta repetida'];
                } elseif ($p && count($propuestas) >= Asistente::MAX_PROPUESTAS) {
                    [$p, $motivo] = [null, 'ya hay '.Asistente::MAX_PROPUESTAS.' propuestas en esta respuesta'];
                }
                if ($p) {
                    $propuestas[] = $p;
                    $claves[$clave] = true;
                } else {
                    Log::info('Asistente: propuesta descartada', ['sesion' => $s->id, 'razon' => $motivo]);
                }
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $u['id'] ?? '', 'content' => $p ? 'propuesta registrada' : "propuesta rechazada: {$motivo}", ...($p ? [] : ['is_error' => true])];
            }
            // The assistant turn goes back unchanged (thinking blocks included), then every result in one user turn.
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        Log::info('Asistente: uso', ['user_id' => $request->user()->id, 'sesion' => $s->id, 'rondas' => min($round, self::MAX_ROUNDS), 'cortado' => $cortado, ...$uso]);

        $texto = implode("\n\n", $textos) ?: ($propuestas ? 'Estas son mis propuestas:' : 'El asistente no generó una respuesta.');

        return response()->json([
            'texto' => $cortado ? $texto."\n\n".self::NOTA_TIEMPO : $texto,
            'propuestas' => $propuestas,
            'uso' => ['input_tokens' => $uso['input_tokens'] + $uso['cache_read_input_tokens'] + $uso['cache_creation_input_tokens'], 'output_tokens' => $uso['output_tokens']],
        ]);
    }

    /** @throws ConnectionException on a timeout or network failure (the caller decides what to keep) */
    private function llamar(array $payload, float $timeout, array $betas): array
    {
        if (config('services.anthropic.provider') === 'openai') {
            return $this->llamarOpenAi($payload, $timeout);
        }
        $key = config('services.anthropic.key');
        if (!$key) {
            Log::error('ANTHROPIC_API_KEY is not set while AI_ASSISTANT_ENABLED is true');
            $this->fail(502, 'El asistente no está configurado. Avisa al administrador.');
        }
        $headers = ['x-api-key' => $key, 'anthropic-version' => config('services.anthropic.api_version')];
        if ($betas) {
            $headers['anthropic-beta'] = implode(',', $betas);
        }
        try {
            return Http::withHeaders($headers)->acceptJson()->connectTimeout(min(5, $timeout))->timeout($timeout)
                ->post(config('services.anthropic.base_url').'/v1/messages', $payload)->throw()->json() ?? [];
        } catch (RequestException $e) {
            $this->fallo($e);
        }
    }

    /** Same contract as llamar(), over an OpenAI-compatible chat API: Anthropic-shaped payload in, Anthropic-shaped reply out. */
    private function llamarOpenAi(array $payload, float $timeout): array
    {
        $key = config('services.anthropic.openai_key');
        if (!$key) {
            Log::error('AI_ASSISTANT_OPENAI_KEY is not set while AI_ASSISTANT_PROVIDER=openai');
            $this->fail(502, 'El asistente no está configurado. Avisa al administrador.');
        }
        $texto = fn ($c) => is_string($c) ? $c : implode("\n\n", array_column(array_filter($c, fn ($b) => ($b['type'] ?? null) === 'text'), 'text'));
        $messages = [['role' => 'system', 'content' => $texto($payload['system'])]];
        foreach ($payload['messages'] as $m) {
            $bloques = is_array($m['content']) ? $m['content'] : [];
            $resultados = array_filter($bloques, fn ($b) => ($b['type'] ?? null) === 'tool_result');
            if ($resultados) {
                foreach ($resultados as $r) {
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $r['tool_use_id'], 'content' => $r['content']];
                }
                continue;
            }
            $llamadas = array_values(array_map(fn ($b) => ['id' => $b['id'], 'type' => 'function', 'function' => ['name' => $b['name'], 'arguments' => json_encode($b['input'] ?? new \stdClass())]],
                array_filter($bloques, fn ($b) => ($b['type'] ?? null) === 'tool_use')));
            $messages[] = ['role' => $m['role'], 'content' => $texto($m['content'])] + ($llamadas ? ['tool_calls' => $llamadas] : []);
        }
        try {
            $res = Http::withToken($key)->acceptJson()->connectTimeout(min(5, $timeout))->timeout($timeout)
                ->post(config('services.anthropic.openai_base_url').'/chat/completions', [
                    'model' => config('services.anthropic.openai_model'),
                    'max_tokens' => $payload['max_tokens'],
                    'messages' => $messages,
                    'tools' => array_map(fn ($t) => ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['input_schema']]], $payload['tools']),
                ])->throw()->json() ?? [];
        } catch (RequestException $e) {
            $this->fallo($e);
        }
        $msg = $res['choices'][0]['message'] ?? [];
        $content = trim($msg['content'] ?? '') !== '' ? [['type' => 'text', 'text' => $msg['content']]] : [];
        foreach ($msg['tool_calls'] ?? [] as $c) {
            $content[] = ['type' => 'tool_use', 'id' => $c['id'] ?? '', 'name' => $c['function']['name'] ?? '', 'input' => json_decode($c['function']['arguments'] ?? '', true)];
        }

        return [
            'content' => $content,
            'stop_reason' => ($res['choices'][0]['finish_reason'] ?? null) === 'tool_calls' ? 'tool_use' : 'end_turn',
            'usage' => ['input_tokens' => $res['usage']['prompt_tokens'] ?? 0, 'output_tokens' => $res['usage']['completion_tokens'] ?? 0],
        ];
    }

    private function fallo(RequestException $e): never
    {
        $status = $e->response->status();
        // Only the API's error type: its message can echo request content.
        Log::error('Asistente failed', ['status' => $status, 'type' => $e->response->json('error.type')]);
        if ($status === 429) {
            $retry = $e->response->header('retry-after');
            throw new HttpResponseException(response()->json(['message' => 'El asistente está recibiendo demasiadas consultas. Intenta nuevamente en un momento.'], 429, $retry !== '' ? ['Retry-After' => $retry] : []));
        }
        $this->fail(502, self::NO_RESPONDIO);
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
