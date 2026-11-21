<?php

namespace App\Http\Controllers\V2;

use App\Domain\Tendencia;
use App\Http\Controllers\Controller;
use App\Models\Datos_entrada;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Gross-error diagnosis of a session (Flask /diagnostico) plus the historical adjustment trend of its process. */
class DiagnosticoController extends Controller
{
    private const TIMEOUT = 30;
    private const CACHE_SECONDS = 600;
    private const KEYS = ['global', 'global_final', 'nodos', 'sospechosos', 'no_redundantes', 'excluidos', 'por_componente'];
    private const NO_DISPONIBLE = 'Diagnóstico no disponible (servicio en actualización).';

    public function show(int $sesion)
    {
        // Only the columns the key and the trend need: datos_entrada is a large longText Flask reads itself.
        $s = Datos_entrada::query()->select(['id', 'proceso_id', 'updated_at'])->find($sesion);
        if (!$s) {
            return response()->json(['message' => 'La sesión no existe.'], 404);
        }

        // updated_at changes on every edit, run and restore, so a stale diagnosis is never served.
        $key = "v2.diagnostico.{$s->id}.".($s->updated_at?->getTimestamp() ?? 0);

        return response()->json(Cache::remember($key, self::CACHE_SECONDS, fn () => [
            'diagnostico' => $this->flask($s->id),
            'tendencia' => Tendencia::deProceso((int) $s->proceso_id),
        ]));
    }

    /** Flask answers plain JSON here (not the double-encoded map of the solver endpoints). */
    private function flask(int $id): array
    {
        $url = config('services.flask.url');
        if (!$url) {
            Log::error('FLASK_API_URL is not set');
            $this->fail(502, 'El solver no está configurado. Avisa al administrador.');
        }

        try {
            $raw = Http::acceptJson()->connectTimeout(5)->timeout(self::TIMEOUT)->post($url.'/diagnostico', ['datos_entrada_id' => $id])->throw()->json();
            foreach (self::KEYS as $k) {
                if (!is_array($raw) || !array_key_exists($k, $raw)) {
                    throw new \UnexpectedValueException("{$k} missing");
                }
            }

            return $raw;
        } catch (ConnectionException $e) {
            Log::error('Diagnostico unreachable', ['error' => $e->getMessage()]);
            $this->fail(503, self::NO_DISPONIBLE);
        } catch (RequestException $e) {
            $status = $e->response->status();
            Log::error('Diagnostico failed', ['status' => $status, 'error' => $e->getMessage()]);
            $error = $e->response->json('error');
            // 404: a Flask without /diagnostico yet (or the row is not visible to it); 503: its DB is down.
            match (true) {
                $status === 422 => $this->fail(422, 'No se pudo calcular el diagnóstico: '.(is_string($error) ? $error : 'los datos no son válidos').'.'),
                in_array($status, [404, 503], true) => $this->fail(503, self::NO_DISPONIBLE),
                default => $this->fail(502, 'El diagnóstico falló en el servidor de cálculo. Intenta nuevamente; si persiste, avisa al administrador.'),
            };
        } catch (\UnexpectedValueException $e) {
            Log::error('Diagnostico returned an invalid payload', ['error' => $e->getMessage()]);
            $this->fail(502, 'El servidor de cálculo devolvió un diagnóstico inválido.');
        }
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
