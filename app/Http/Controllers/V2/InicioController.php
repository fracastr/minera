<?php

namespace App\Http\Controllers\V2;

use App\Domain\Calidad;
use App\Domain\SesionMapper;
use App\Domain\Tendencia;
use App\Http\Controllers\Controller;
use App\Models\BalanceCorrida;
use App\Models\Datos_entrada;
use App\Models\Procesos;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Control center: the user's recent sessions, plus alerts and recurring meter bias over the saved balances. */
class InicioController extends Controller
{
    private const RECIENTES = 6;
    private const CACHE_SECONDS = 600;

    public function show(Request $request)
    {
        // Stamp of the saved sessions: changes when one is added, linked to a balance, saved through Eloquent
        // (updated_at) or gets a new solver status. A raw write that skips all of these shows up when the TTL expires.
        $stamp = DB::table('datos_entradas')->whereNotNull('balance_id')
            ->selectRaw('COUNT(*) as n, MAX(id) as i, MAX(updated_at) as t, MAX(balance_id) as b')->first();
        $solver = DB::table('datos_entradas')->whereNotNull('balance_id')->whereNotNull('solver_estado')
            ->orderBy('id')->pluck('solver_estado', 'id');
        $key = 'v2.inicio.'.md5(json_encode([$stamp, $solver]));

        return response()->json([
            'recientes' => $this->recientes($request->user()->id),
            ...$this->global($key),
        ]);
    }

    /** Alerts and bias are the same for everyone: computed once per stamp, one request at a time (no stampede). */
    private function global(string $key): array
    {
        $compute = fn () => ['alertas' => $this->alertas(), 'sesgos' => $this->sesgos()];
        if (($hit = Cache::get($key)) !== null) {
            return $hit;
        }
        try {
            return Cache::lock('v2.inicio.lock', 60)->block(20, fn () => Cache::remember($key, self::CACHE_SECONDS, $compute));
        } catch (LockTimeoutException) {
            return $compute(); // the holder is slow: answer anyway rather than fail the page
        }
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
        // Correlated subquery instead of a window function: runs on MySQL 5.7+ and SQLite alike.
        $ultimos = DB::table('balances as b')->whereNotNull('b.proceso_id')
            ->whereRaw('b.id = (SELECT b2.id FROM balances b2 WHERE b2.proceso_id = b.proceso_id
                ORDER BY COALESCE(b2.periodo_fin, b2.created_at) DESC, b2.id DESC LIMIT 1)')
            ->orderBy('b.proceso_id')
            ->get(['b.id', 'b.nombre', 'b.proceso_id', 'b.periodo_inicio', 'b.periodo_fin']);
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
            // One unreadable stored row must not take the whole page down.
            try {
                $calidad = Calidad::resumen(SesionMapper::toDto(SesionMapper::decode($s->datos_entrada), []));
            } catch (\Throwable $e) {
                Log::warning('Inicio: skipped a balance whose data cannot be read', ['balance_id' => $b->id, 'sesion_id' => $s->id, 'error' => $e->getMessage()]);

                return null;
            }

            return [
                'balance_id' => $b->id,
                'sesion_id' => $s->id,
                'nombre' => $b->nombre,
                'proceso' => $p?->nombre,
                'valle' => $p?->valle?->nombre,
                'periodo_inicio' => $b->periodo_inicio ? substr((string) $b->periodo_inicio, 0, 10) : null,
                'periodo_fin' => $b->periodo_fin ? substr((string) $b->periodo_fin, 0, 10) : null,
                ...$calidad,
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
            try {
                $tendencia = Tendencia::deProceso((int) $id);
            } catch (\Throwable $e) {
                Log::warning('Inicio: skipped the trend of a process whose data cannot be read', ['proceso_id' => $id, 'error' => $e->getMessage()]);
                continue;
            }
            foreach ($tendencia as $t) {
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
