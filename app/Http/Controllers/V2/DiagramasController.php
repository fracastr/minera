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

        return response()->json(['layout' => $layout === null ? null : json_decode($layout)]);
    }

    public function update(Request $request, Procesos $proceso)
    {
        $layout = $request->input('layout');
        if (! is_array($layout) || count($layout) > self::MAX_NODOS || ($layout && array_is_list($layout))) {
            throw ValidationException::withMessages(['layout' => 'La disposición debe ser un objeto {nodo: {x, y}} de a lo más '.self::MAX_NODOS.' nodos.']);
        }
        $finite = fn ($v) => (is_int($v) || is_float($v)) && is_finite($v) && abs($v) < 1e6;
        foreach ($layout as $nodo => $pos) {
            $ok = is_string($nodo) && mb_strlen($nodo) <= 191 && is_array($pos) && count($pos) === 2
                && $finite($pos['x'] ?? null) && $finite($pos['y'] ?? null);
            if (! $ok) {
                throw ValidationException::withMessages(['layout' => "Posición inválida para el nodo «{$nodo}»."]);
            }
        }

        // An empty layout means "back to automatic": forget the saved one.
        if (! $layout) {
            DB::table('proceso_diagramas')->where('proceso_id', $proceso->id)->delete();

            return response()->json(['layout' => null]);
        }

        $clean = (object) array_map(fn ($p) => ['x' => round((float) $p['x'], 1), 'y' => round((float) $p['y'], 1)], $layout);
        DB::table('proceso_diagramas')->updateOrInsert(
            ['proceso_id' => $proceso->id],
            fn (bool $exists) => ['layout' => json_encode($clean), 'updated_by' => $request->user()->id, 'updated_at' => now()]
                + ($exists ? [] : ['created_at' => now()]),
        );

        return response()->json(['layout' => $clean]);
    }
}
