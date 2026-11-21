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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A "sesión" is one datos_entradas row: the imported Excel plus every edit and run on it.
 * A saved balance points at its latest session (balances ← datos_entradas.balance_id).
 */
class SesionesController extends Controller
{
    private const SOLVER_TIMEOUT = 180;

    public function importar(Request $request)
    {
        $request->validate([
            'proceso_id' => 'required|integer|exists:procesos,id',
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);
        $user = $request->user();
        $proceso = Procesos::findOrFail($request->integer('proceso_id'));

        // nombre_original_timestamp_userid.ext: the listado reads the owner back from this name.
        $file = $request->file('file');
        $base = preg_replace('/[^\w.-]+/u', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $fileName = $base.'_'.now()->format('YmdHis').'_'.$user->id.'.'.strtolower($file->getClientOriginalExtension());
        $stored = $file->storeAs('public', $fileName);

        $res = $this->solver('/get_balance', ['path_name' => Storage::path($stored), 'nombre_proceso' => $proceso->nombre]);

        $datos = $res['datos_entrada'];
        $datos['desviaciones'] = $res['desviaciones'];
        $datos['nodos_data'] = $res['nodos_data'];

        $sesion = Datos_entrada::create([
            'datos_entrada' => json_encode($datos),
            'proceso_id' => $proceso->id,
            'valle_id' => $proceso->valle_id,
            'file_path' => $fileName,
            'user_id' => $user->id,
        ]);
        BalanceEventService::logImported($sesion, $user);
        BalanceCorrida::record($sesion, $user, BalanceCorrida::TIPO_IMPORTACION);

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

        $res = $this->solver('/correr_balance', ['datos_entrada_id' => $s->id]);

        $datos = $res['datos_entrada'];
        $datos['mediciones'] = $res['balances_finales'];
        $datos['desviaciones'] = $res['desviaciones'];
        $datos['nodos_data'] = $res['nodos_data'];
        $s->datos_entrada = json_encode($datos);
        $s->save();

        BalanceEventService::logCorrer($s, $request->user());
        BalanceCorrida::record($s, $request->user(), BalanceCorrida::TIPO_CORRIDA);

        return response()->json($this->dto($s));
    }

    public function clonar(Request $request, int $sesion)
    {
        $original = $this->find($sesion);
        $clone = $original->replicate();
        $clone->balance_id = null;
        $clone->user_id = $request->user()->id;
        $clone->save();
        BalanceCorrida::copyHistory($original->id, $clone->id);

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

        return response()->json(['corridas' => $rows->values()->map(fn (BalanceCorrida $c, int $i) => [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'usuario' => $c->user ? ['id' => $c->user->id, 'nombre' => $c->user->nombre] : null,
            'created_at' => $c->created_at?->toIso8601String(),
            'actual' => $i === 0, // every change to the session records a version, so the newest is what is loaded
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

        $s->datos_entrada = $c->snapshot;
        $s->save();
        BalanceCorrida::record($s, $request->user(), BalanceCorrida::TIPO_RESTAURACION);

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
        if ($v['sobrescribir']) {
            $balance = $s->balance_id ? Balances::find($s->balance_id) : null;
            if (!$balance) {
                return response()->json(['message' => 'Esta sesión no tiene un balance guardado que sobrescribir.'], 422);
            }
            $balance->fill($campos + ['proceso_id' => $proceso->id])->save();
        } else {
            // Re-pointing a saved session would leave its balance without data: clone it first.
            if ($s->balance_id) {
                return response()->json(['message' => 'Esta sesión ya pertenece a un balance. Guárdala como copia o sobrescríbelo.'], 422);
            }
            $balance = Balances::create($campos + ['proceso_id' => $proceso->id, 'user_id' => $request->user()->id]);
        }

        $s->balance_id = $balance->id;
        $s->valle_id = $proceso->valle_id;
        $s->user_id = $s->user_id ?: $request->user()->id;
        $s->save();
        BalanceEventService::logSaved($s, $request->user(), $balance->id);

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
        $s->datos_entrada = json_encode(SesionMapper::applyEdits($datos, $edits));
        $s->save();
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
            ...SesionMapper::toDto(SesionMapper::decode($s->datos_entrada), $componentes),
            'updated_at' => $s->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Calls the Flask solver. Its responses are a map of JSON-encoded strings; this returns them decoded.
     * Any failure becomes a 502 with a message the user can act on.
     */
    private function solver(string $path, array $body): array
    {
        try {
            $raw = Http::acceptJson()->timeout(self::SOLVER_TIMEOUT)->post(config('services.flask.url').$path, $body)->throw()->json();
            $out = [];
            foreach (['datos_entrada', 'desviaciones', 'nodos_data', 'balances_finales'] as $key) {
                if (array_key_exists($key, $raw ?? [])) {
                    $out[$key] = is_string($raw[$key]) ? json_decode($raw[$key], true, 512, JSON_THROW_ON_ERROR) : $raw[$key];
                }
            }
            if (!is_array($out['datos_entrada'] ?? null)) {
                throw new \UnexpectedValueException('datos_entrada missing');
            }

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
}
