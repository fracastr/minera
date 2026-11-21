<?php

namespace App\Http\Controllers\V2;

use App\Domain\Asistente;
use App\Http\Controllers\Controller;
use App\Services\DiagnosticoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Balance assistant: one chat turn over the session and its cached diagnosis, through the Claude Messages API.
 * The model may call `proponer_accion`; valid proposals are returned to the client, never executed here.
 */
class AsistenteController extends Controller
{
    private const TIMEOUT = 90;
    private const MAX_ROUNDS = 3;
    private const MAX_TOKENS = 4096;
    private const API_VERSION = '2026-10-21';
    /** Server-side refusal fallback (claude-api skill default for Sonnet 5.5): a classifier decline is retried on the recommended model. */
    private const BETA = 'server-side-fallback-2026-07-01';
    private const NO_RESPONDIO = 'El asistente no respondió. Intenta nuevamente en unos segundos.';

    public function responder(Request $request, int $sesion)
    {
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

        $s = SesionesController::find($sesion);
        $dto = SesionesController::dto($s);
        $contexto = Asistente::contexto($dto, DiagnosticoService::deSesion($s));

        $messages = array_map(fn ($m) => ['role' => $m['rol'] === 'usuario' ? 'user' : 'assistant', 'content' => $m['texto']], $body['mensajes']);
        $base = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => self::MAX_TOKENS,
            'output_config' => ['effort' => 'medium'],
            'fallbacks' => 'default',
            'tools' => [Asistente::TOOL],
            // Render order is tools → system → messages: the breakpoint on the context caches the tool, the prompt and
            // the context, which stay byte-identical across the turns of a conversation on one session version.
            'system' => [
                ['type' => 'text', 'text' => Asistente::SYSTEM],
                ['type' => 'text', 'text' => "Contexto de la sesión (JSON):\n".$contexto, 'cache_control' => ['type' => 'ephemeral']],
            ],
        ];

        $textos = [];
        $propuestas = [];
        $uso = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0];
        for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
            $res = $this->llamar($base + ['messages' => $messages]);
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
                [$p, $motivo] = ($u['name'] ?? null) === 'proponer_accion' ? Asistente::validarPropuesta($u['input'] ?? null, $dto) : [null, 'herramienta desconocida'];
                if ($p) {
                    $propuestas[] = $p;
                } else {
                    Log::info('Asistente: propuesta descartada', ['sesion' => $s->id, 'razon' => $motivo]);
                }
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $u['id'] ?? '', 'content' => $p ? 'propuesta registrada' : "propuesta rechazada: {$motivo}", ...($p ? [] : ['is_error' => true])];
            }
            // The assistant turn goes back unchanged (thinking blocks included), then every result in one user turn.
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        Log::info('Asistente: uso', ['user_id' => $request->user()->id, 'sesion' => $s->id, 'rondas' => min($round, self::MAX_ROUNDS), ...$uso]);

        return response()->json([
            'texto' => implode("\n\n", $textos) ?: ($propuestas ? 'Estas son mis propuestas:' : 'El asistente no generó una respuesta.'),
            'propuestas' => $propuestas,
            'uso' => ['input_tokens' => $uso['input_tokens'] + $uso['cache_read_input_tokens'] + $uso['cache_creation_input_tokens'], 'output_tokens' => $uso['output_tokens']],
        ]);
    }

    private function llamar(array $payload): array
    {
        $key = config('services.anthropic.key');
        if (!$key) {
            Log::error('ANTHROPIC_API_KEY is not set while AI_ASSISTANT_ENABLED is true');
            $this->fail(502, 'El asistente no está configurado. Avisa al administrador.');
        }
        try {
            return Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => self::API_VERSION, 'anthropic-beta' => self::BETA])
                ->acceptJson()->connectTimeout(5)->timeout(self::TIMEOUT)
                ->post(config('services.anthropic.base_url').'/v1/messages', $payload)->throw()->json() ?? [];
        } catch (ConnectionException $e) {
            Log::error('Asistente unreachable', ['error' => $e->getMessage()]);
            $this->fail(502, self::NO_RESPONDIO);
        } catch (RequestException $e) {
            $status = $e->response->status();
            // Only the API's error type: its message can echo request content.
            Log::error('Asistente failed', ['status' => $status, 'type' => $e->response->json('error.type')]);
            if ($status === 429) {
                $retry = $e->response->header('retry-after');
                throw new HttpResponseException(response()->json(['message' => 'El asistente está recibiendo demasiadas consultas. Intenta nuevamente en un momento.'], 429, $retry !== '' ? ['Retry-After' => $retry] : []));
            }
            $this->fail(502, self::NO_RESPONDIO);
        }
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
