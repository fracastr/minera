<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Procesos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Saved node positions of each process flowsheet (proceso_diagramas). */
class DiagramasController extends Controller
{
    private const MAX_NODOS = 500;

    /** Every process and whether it has a saved layout (admin page). */
    public function index()
    {
        $saved = DB::table('proceso_diagramas')
            ->leftJoin('users', 'users.id', '=', 'proceso_diagramas.updated_by')
            ->get(['proceso_diagramas.proceso_id', 'proceso_diagramas.updated_at', 'users.nombre as usuario'])
            ->keyBy('proceso_id');

        $procesos = Procesos::query()->with('valle:id,nombre')->orderBy('valle_id')->orderBy('id')->get(['id', 'nombre', 'valle_id'])
            ->map(function (Procesos $p) use ($saved) {
                $d = $saved[$p->id] ?? null;

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'valle' => $p->valle?->nombre,
                    'guardado' => (bool) $d,
                    'updated_at' => $d?->updated_at ? Carbon::parse($d->updated_at)->toIso8601String() : null,
                    'usuario' => $d?->usuario,
                ];
            });

        return response()->json(['procesos' => $procesos]);
    }

    public function show(Procesos $proceso)
    {
        $layout = DB::table('proceso_diagramas')->where('proceso_id', $proceso->id)->value('layout');

        return response()->json(['layout' => $layout === null ? null : self::entries(json_decode($layout, true))]);
    }

    /**
     * Layout as [{k, x, y}] entries: a JSON object keyed by node name would turn names like "1" into PHP ints.
     * Rows saved before this format hold {nodo: {x, y}} and are read as entries too.
     */
    private static function entries(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }
        if (array_is_list($stored)) {
            return $stored;
        }

        return array_map(fn ($k, $p) => ['k' => (string) $k, 'x' => $p['x'] ?? 0, 'y' => $p['y'] ?? 0], array_keys($stored), $stored);
    }

    public function update(Request $request, Procesos $proceso)
    {
        $layout = $request->input('layout');
        if (! is_array($layout) || ! array_is_list($layout) || count($layout) > self::MAX_NODOS) {
            throw ValidationException::withMessages(['layout' => 'La disposición debe ser una lista [{k, x, y}] de a lo más '.self::MAX_NODOS.' nodos.']);
        }
        $finite = fn ($v) => (is_int($v) || is_float($v)) && is_finite($v) && abs($v) < 1e6;
        foreach ($layout as $i => $e) {
            $ok = is_array($e) && count($e) === 3 && is_string($e['k'] ?? null) && mb_strlen($e['k']) <= 191
                && $finite($e['x'] ?? null) && $finite($e['y'] ?? null);
            if (! $ok) {
                throw ValidationException::withMessages(['layout' => 'Posición inválida en la entrada '.($i + 1).'.']);
            }
        }

        // An empty layout means "back to automatic": forget the saved one.
        if (! $layout) {
            DB::table('proceso_diagramas')->where('proceso_id', $proceso->id)->delete();

            return response()->json(['layout' => null]);
        }

        $clean = array_map(fn ($e) => ['k' => $e['k'], 'x' => round((float) $e['x'], 1), 'y' => round((float) $e['y'], 1)], $layout);
        $now = now();
        // One statement, so two saves at once cannot both insert.
        DB::table('proceso_diagramas')->upsert(
            [['proceso_id' => $proceso->id, 'layout' => json_encode($clean), 'updated_by' => $request->user()->id, 'created_at' => $now, 'updated_at' => $now]],
            ['proceso_id'],
            ['layout', 'updated_by', 'updated_at'],
        );

        return response()->json(['layout' => $clean]);
    }
}
