<?php

namespace App\Http\Controllers\V2;

use App\Domain\Calidad;
use App\Domain\SesionMapper;
use App\Domain\Tendencia;
use App\Http\Controllers\Controller;
use App\Models\BalanceCorrida;
use App\Models\Datos_entrada;
use App\Models\Procesos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Control center: the user's recent sessions, plus alerts and recurring meter bias over the saved balances. */
class InicioController extends Controller
{
    private const RECIENTES = 6;
    private const CACHE_SECONDS = 600;

    public function show(Request $request)
    {
        // Any save, overwrite or edit of a saved session changes this stamp, so the cached part is never stale.
        $stamp = DB::table('datos_entradas')->whereNotNull('balance_id')
            ->selectRaw('COUNT(*) as n, MAX(updated_at) as t, MAX(balance_id) as b')->first();
        $key = 'v2.inicio.'.md5(json_encode($stamp));

        return response()->json([
            'recientes' => $this->recientes($request->user()->id),
            ...Cache::remember($key, self::CACHE_SECONDS, fn () => [
                'alertas' => $this->alertas(),
                'sesgos' => $this->sesgos(),
            ]),
        ]);
    }

    private function recientes(int $userId): array
    {
        // datos_entrada is needed only to check the stored solver status against its hash (6 rows).
        $sesiones = Datos_entrada::query()
            ->select(['id', 'balance_id', 'proceso_id', 'updated_at', 'solver_estado', 'datos_entrada'])
            ->with(['balance:id,nombre', 'proceso:id,nombre,valle_id', 'proceso.valle:id,nombre'])
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->limit(self::RECIENTES)
            ->get();
        $corridas = BalanceCorrida::query()->where('tipo', BalanceCorrida::TIPO_CORRIDA)
            ->whereIn('datos_entrada_id', $sesiones->pluck('id'))->distinct()->pluck('datos_entrada_id')->flip();

        return $sesiones->map(fn (Datos_entrada $s) => [
            'id' => $s->id,
            'nombre' => $s->balance?->nombre,
            'proceso' => $s->proceso?->nombre,
            'valle' => $s->proceso?->valle?->nombre,
            'estado' => $s->balance_id ? 'guardado' : (isset($corridas[$s->id]) ? 'corrido' : 'sin-correr'),
            'solver_ok' => SesionesController::solverVigente($s)['ok'] ?? null,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ])->all();
    }

    /** The latest saved balance of each process (by period end, as the trend orders them) and its quality counts. */
    private function alertas(): array
    {
        $ultimos = DB::table('balances')
            ->orderByRaw('COALESCE(periodo_fin, created_at) DESC')->orderByDesc('id')
            ->get(['id', 'nombre', 'proceso_id', 'periodo_inicio', 'periodo_fin'])
            ->unique('proceso_id');
        // A balance points at its newest session.
        $sesionIds = DB::table('datos_entradas')->whereIn('balance_id', $ultimos->pluck('id'))
            ->groupBy('balance_id')->selectRaw('MAX(id) as id, balance_id')->pluck('id', 'balance_id');
        $sesiones = Datos_entrada::query()->whereIn('id', $sesionIds->values())
            ->get(['id', 'datos_entrada', 'solver_estado'])->keyBy('id');
        $procesos = $this->procesos($ultimos->pluck('proceso_id'));

        return $ultimos->map(function ($b) use ($sesionIds, $sesiones, $procesos) {
            $s = $sesiones[$sesionIds[$b->id] ?? 0] ?? null;
            if (!$s) {
                return null;
            }
            $p = $procesos[$b->proceso_id] ?? null;

            return [
                'balance_id' => $b->id,
                'sesion_id' => $s->id,
                'nombre' => $b->nombre,
                'proceso' => $p?->nombre,
                'valle' => $p?->valle?->nombre,
                'periodo_inicio' => $b->periodo_inicio ? substr((string) $b->periodo_inicio, 0, 10) : null,
                'periodo_fin' => $b->periodo_fin ? substr((string) $b->periodo_fin, 0, 10) : null,
                ...Calidad::resumen(SesionMapper::toDto(SesionMapper::decode($s->datos_entrada), [])),
                'solver_ok' => SesionesController::solverVigente($s)['ok'] ?? null,
            ];
        })->filter()->values()->all();
    }

    /** Meters with a recurring bias, per process that has saved balances. */
    private function sesgos(): array
    {
        $ids = DB::table('balances')->whereNotNull('proceso_id')->distinct()->pluck('proceso_id');
        $procesos = $this->procesos($ids);
        $out = [];
        foreach ($ids as $id) {
            $p = $procesos[$id] ?? null;
            $componentes = json_decode($p?->componentes ?? '', true)['data'] ?? [];
            foreach (Tendencia::deProceso((int) $id) as $t) {
                $vars = [['TMS', $t], ...array_map(fn ($ley, $k) => [$componentes[$k] ?? 'Ley '.($k + 1), $ley], $t['leyes'], array_keys($t['leyes']))];
                foreach ($vars as [$variable, $v]) {
                    if (!$v['sesgo_recurrente']) {
                        continue;
                    }
                    $out[] = [
                        'proceso_id' => (int) $id,
                        'proceso' => $p?->nombre,
                        'valle' => $p?->valle?->nombre,
                        'flujo' => $t['flujo'],
                        'tag' => $t['tag'],
                        'variable' => $variable,
                        'signo' => $v['signo'],
                        'ultimo_ajuste_pct' => end($v['serie'])['ajuste_pct'] ?? null,
                        'balances' => count($v['serie']),
                    ];
                }
            }
        }

        return $out;
    }

    private function procesos($ids)
    {
        return Procesos::query()->with('valle:id,nombre')->whereIn('id', $ids)->get(['id', 'nombre', 'valle_id', 'componentes'])->keyBy('id');
    }
}
