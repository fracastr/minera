<?php

namespace App\Http\Controllers\V2;

use App\Domain\SesionMapper;
use App\Http\Controllers\Controller;
use App\Models\BalanceCorrida;
use App\Models\Balances;
use App\Models\Datos_entrada;
use App\Models\Procesos;
use App\Services\BalanceEventService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A "sesión" is one datos_entradas row: the imported Excel plus every edit and run on it.
 * A saved balance points at its latest session (balances ← datos_entradas.balance_id).
 */
class SesionesController extends Controller
{
    /**
     * Same as v1, which calls Flask with Laravel's default 30 s client timeout and has run that way in production
     * (Apache + PHP-FPM, whose request limits are well above it). Raise both together if a process needs longer.
     */
    private const SOLVER_TIMEOUT = 30;

    /** Uploads live on the private local disk: Flask only needs the absolute path, nothing serves them. */
    private const UPLOAD_DIR = 'balances-carga';

    /** Response keys each solver endpoint must return. */
    private const SOLVER_KEYS = [
        '/get_balance' => ['datos_entrada', 'desviaciones', 'nodos_data'],
        '/correr_balance' => ['datos_entrada', 'desviaciones', 'nodos_data', 'balances_finales'],
    ];

    public function importar(Request $request)
    {
        $request->validate([
            'proceso_id' => 'required|integer|exists:procesos,id',
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);
        $user = $request->user();
        $proceso = Procesos::findOrFail($request->integer('proceso_id'));

        // The extension comes from the content, never from the client's file name.
        $file = $request->file('file');
        $ext = $file->guessExtension();
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return response()->json(['message' => 'El archivo debe ser una planilla Excel (.xlsx o .xls).', 'errors' => ['file' => ['El archivo debe ser una planilla Excel (.xlsx o .xls).']]], 422);
        }
        // nombre_YYYYmmddHHMMSS_userid.ext: Balances::creador() reads the owner back from this suffix.
        $base = mb_substr(preg_replace('/[^\w.-]+/u', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 0, 100) ?: 'carga';
        $fileName = $base.'_'.now()->format('YmdHis').'_'.$user->id.'.'.$ext;
        $stored = $file->storeAs(self::UPLOAD_DIR, $fileName, 'local');

        try {
            $res = $this->solver('/get_balance', ['path_name' => Storage::disk('local')->path($stored), 'nombre_proceso' => $proceso->nombre]);
            $datos = $res['datos_entrada'];
            $datos['desviaciones'] = $res['desviaciones'];
            $datos['nodos_data'] = $res['nodos_data'];
            $json = $this->encodeSolverResult($datos);

            $sesion = DB::transaction(function () use ($json, $proceso, $fileName, $user, $res) {
                $sesion = new Datos_entrada([
                    'datos_entrada' => $json,
                    'proceso_id' => $proceso->id,
                    'valle_id' => $proceso->valle_id,
                    'file_path' => $fileName,
                    'user_id' => $user->id,
                ]);
                $sesion->solver_estado = $res['solver'];
                $sesion->save();
                BalanceCorrida::record($sesion, $user, BalanceCorrida::TIPO_IMPORTACION);
                BalanceEventService::logImported($sesion, $user);

                return $sesion;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored); // nothing points at a workbook whose import failed
            throw $e;
        }

        return response()->json(['id' => $sesion->id], 201);
    }

    public function show(int $sesion)
    {
        return response()->json($this->dto($this->find($sesion)));
    }

    public function guardarDatos(Request $request, int $sesion)
    {
        $s = $this->find($sesion);
        $this->persistEdits($request, $s);

        return response()->json($this->dto($s));
    }

    public function correr(Request $request, int $sesion)
    {
        $s = $this->find($sesion);
        $this->persistEdits($request, $s); // the solver reads the row itself, so edits go first
        $sent = md5((string) $s->datos_entrada);

        $res = $this->solver('/correr_balance', ['datos_entrada_id' => $s->id]);
        $datos = $res['datos_entrada'];
        $datos['mediciones'] = $res['balances_finales'];
        $datos['desviaciones'] = $res['desviaciones'];
        $datos['nodos_data'] = $res['nodos_data'];
        $json = $this->encodeSolverResult($datos);

        DB::transaction(function () use ($s, $sent, $json, $request, $res) {
            // Someone else saved, ran or restored this session while the solver worked: keep their version.
            $current = Datos_entrada::query()->lockForUpdate()->findOrFail($s->id);
            if (md5((string) $current->datos_entrada) !== $sent) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Otra persona modificó esta sesión mientras se corría el balance. Recarga para ver sus cambios y vuelve a correr.',
                ], 409));
            }
            $s->datos_entrada = $json;
            $s->solver_estado = $res['solver'];
            $s->save();
            BalanceCorrida::record($s, $request->user(), BalanceCorrida::TIPO_CORRIDA);
            BalanceEventService::logCorrer($s, $request->user());
        });

        return response()->json($this->dto($s));
    }

    public function clonar(Request $request, int $sesion)
    {
        $original = $this->find($sesion);
        $clone = DB::transaction(function () use ($original, $request) {
            $clone = $original->replicate();
            $clone->balance_id = null;
            $clone->user_id = $request->user()->id;
            $clone->save();
            BalanceCorrida::copyHistory($original->id, $clone->id);

            return $clone;
        });

        return response()->json(['id' => $clone->id], 201);
    }

    public function corridas(int $sesion)
    {
        $s = $this->find($sesion);
        $rows = BalanceCorrida::query()
            ->select(['id', 'tipo', 'user_id', 'created_at'])
            ->with('user:id,nombre')
            ->where('datos_entrada_id', $s->id)
            ->orderByDesc('id')
            ->get();

        // `actual` marks the newest recorded version; edits saved after it (PUT datos, Guardar) are not versions.
        return response()->json(['corridas' => $rows->values()->map(fn (BalanceCorrida $c, int $i) => [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'usuario' => $c->user ? ['id' => $c->user->id, 'nombre' => $c->user->nombre] : null,
            'created_at' => $c->created_at?->toIso8601String(),
            'actual' => $i === 0,
        ])]);
    }

    public function restaurar(Request $request, int $sesion, int $corrida)
    {
        $s = $this->find($sesion);
        $c = BalanceCorrida::with('datosEntrada:id,proceso_id')->find($corrida);
        if (!$c) {
            return response()->json(['message' => 'La versión no existe.'], 404);
        }
        // A clone carries copies of its source's history, so only the process has to match.
        if ((int) $c->datosEntrada?->proceso_id !== (int) $s->proceso_id) {
            return response()->json(['message' => 'La versión pertenece a otro proceso.'], 422);
        }

        DB::transaction(function () use ($s, $c, $request) {
            $s->datos_entrada = $c->snapshot;
            $s->solver_estado = null; // the snapshot does not say how its run converged
            $s->save();
            BalanceCorrida::record($s, $request->user(), BalanceCorrida::TIPO_RESTAURACION);
        });

        return response()->json($this->dto($s));
    }

    public function guardar(Request $request, int $sesion)
    {
        $s = $this->find($sesion);
        $v = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:'.implode(',', Balances::TIPOS),
            'periodo_inicio' => 'required|date',
            'periodo_fin' => 'required|date|after_or_equal:periodo_inicio',
            'sobrescribir' => 'required|boolean',
        ]);
        $proceso = Procesos::find($s->proceso_id);
        if (!$proceso) {
            return response()->json(['message' => 'La sesión no tiene un proceso válido.'], 422);
        }

        $campos = ['nombre' => $v['nombre'], 'tipo' => $v['tipo'], 'periodo_inicio' => $v['periodo_inicio'], 'periodo_fin' => $v['periodo_fin']];
        $actual = $s->balance_id ? Balances::find($s->balance_id) : null; // null too when balance_id points at a deleted balance
        if ($v['sobrescribir'] && !$actual) {
            return response()->json(['message' => 'Esta sesión no tiene un balance guardado que sobrescribir.'], 422);
        }
        // Re-pointing a saved session would leave its balance without data: clone it first.
        if (!$v['sobrescribir'] && $actual) {
            return response()->json(['message' => 'Esta sesión ya pertenece a un balance. Guárdala como copia o sobrescríbelo.'], 422);
        }

        $balance = DB::transaction(function () use ($v, $actual, $campos, $proceso, $s, $request) {
            $balance = $v['sobrescribir']
                ? tap($actual)->update($campos + ['proceso_id' => $proceso->id])
                : Balances::create($campos + ['proceso_id' => $proceso->id, 'user_id' => $request->user()->id]);
            $s->balance_id = $balance->id;
            $s->valle_id = $proceso->valle_id;
            $s->user_id = $s->user_id ?: $request->user()->id;
            $s->save();
            BalanceEventService::logSaved($s, $request->user(), $balance->id);

            return $balance;
        });

        return response()->json(['balance_id' => $balance->id]);
    }

    private function find(int $id): Datos_entrada
    {
        $s = Datos_entrada::query()->find($id);
        if (!$s) {
            throw new HttpResponseException(response()->json(['message' => 'La sesión no existe.'], 404));
        }

        return $s;
    }

    private function persistEdits(Request $request, Datos_entrada $s): void
    {
        $datos = SesionMapper::decode($s->datos_entrada);
        $edits = $request->validate(SesionMapper::editRules($datos));
        if (!$edits) {
            return;
        }
        if ($error = SesionMapper::shapeError($datos)) {
            throw new HttpResponseException(response()->json(['message' => $error], 422));
        }
        try {
            $s->datos_entrada = SesionMapper::encode(SesionMapper::applyEdits($datos, $edits));
        } catch (\JsonException) {
            throw new HttpResponseException(response()->json(['message' => 'Los datos editados no se pudieron guardar: hay valores no válidos.'], 422));
        }
        $s->save();
    }

    private function encodeSolverResult(array $datos): string
    {
        try {
            return SesionMapper::encode($datos);
        } catch (\JsonException $e) {
            Log::error('Solver result could not be encoded', ['error' => $e->getMessage()]);
            throw new HttpResponseException(response()->json(['message' => 'El solver devolvió valores no válidos (infinitos o indefinidos). Revisa las mediciones.'], 502));
        }
    }

    private function dto(Datos_entrada $s): array
    {
        $s->loadMissing(['proceso:id,nombre,valle_id,componentes', 'proceso.valle:id,nombre', 'balance']);
        $componentes = json_decode($s->proceso?->componentes ?? '', true)['data'] ?? [];
        $b = $s->balance;

        return [
            'id' => $s->id,
            'balance' => $b ? [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'tipo' => $b->tipo,
                'periodo_inicio' => optional($b->periodo_inicio)->format('Y-m-d'),
                'periodo_fin' => optional($b->periodo_fin)->format('Y-m-d'),
            ] : null,
            'proceso' => [
                'id' => $s->proceso?->id,
                'nombre' => $s->proceso?->nombre,
                'valle' => ['id' => $s->proceso?->valle?->id, 'nombre' => $s->proceso?->valle?->nombre],
            ],
            'corrida' => BalanceCorrida::where('datos_entrada_id', $s->id)->where('tipo', BalanceCorrida::TIPO_CORRIDA)->exists(),
            // ok=false: GEKKO did not converge and the reconciled values are clamped to the bounds, not trustworthy
            'solver' => $s->solver_estado,
            ...SesionMapper::toDto(SesionMapper::decode($s->datos_entrada), $componentes),
            'updated_at' => $s->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Calls the Flask solver. Its responses are a map of JSON-encoded strings; this returns them decoded,
     * with every key the endpoint must send. Any failure becomes a 502 with a message the user can act on.
     */
    private function solver(string $path, array $body): array
    {
        $url = config('services.flask.url');
        if (!$url) {
            Log::error('FLASK_API_URL is not set');
            throw new HttpResponseException(response()->json(['message' => 'El solver no está configurado. Avisa al administrador.'], 502));
        }

        try {
            $raw = Http::acceptJson()->connectTimeout(5)->timeout(self::SOLVER_TIMEOUT)->post($url.$path, $body)->throw()->json();
            $out = [];
            foreach (self::SOLVER_KEYS[$path] as $key) {
                if (!is_array($raw) || !array_key_exists($key, $raw)) {
                    throw new \UnexpectedValueException("{$key} missing");
                }
                $value = is_string($raw[$key]) ? json_decode($raw[$key], true, 512, JSON_THROW_ON_ERROR) : $raw[$key];
                if (!is_array($value)) {
                    throw new \UnexpectedValueException("{$key} is not a list or map");
                }
                $out[$key] = $value;
            }
            $out['solver'] = self::solverEstado($raw['solver'] ?? null);

            return $out;
        } catch (ConnectionException $e) {
            Log::error("Solver {$path} unreachable", ['error' => $e->getMessage()]);
            $message = 'El solver no respondió. Intenta nuevamente en unos minutos; si persiste, avisa al administrador.';
        } catch (RequestException $e) {
            Log::error("Solver {$path} failed", ['status' => $e->response->status(), 'error' => $e->getMessage()]);
            $message = $path === '/get_balance'
                ? 'El solver no pudo leer el Excel. Revisa que tenga el formato del proceso elegido.'
                : 'El solver no pudo calcular el balance con estos datos. Revisa las mediciones editadas.';
        } catch (\JsonException|\UnexpectedValueException|\TypeError $e) {
            Log::error("Solver {$path} returned an invalid payload", ['error' => $e->getMessage()]);
            $message = 'El solver devolvió una respuesta inválida. Revisa que el Excel tenga el formato del proceso.';
        }

        throw new HttpResponseException(response()->json(['message' => $message], 502));
    }

    /**
     * GEKKO status from the solver's optional `solver` key (a JSON string like the others).
     * Missing (Flask before the status was added) or unreadable means unknown: null, never an error.
     */
    private static function solverEstado(mixed $raw): ?array
    {
        $s = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($s) || !array_key_exists('ok', $s)) {
            return null;
        }

        return [
            'ok' => (bool) $s['ok'],
            'mensaje' => isset($s['mensaje']) && is_scalar($s['mensaje']) ? mb_substr((string) $s['mensaje'], 0, 500) : null,
            'iteraciones' => isset($s['iteraciones']) && is_numeric($s['iteraciones']) ? (int) $s['iteraciones'] : null,
        ];
    }
}
