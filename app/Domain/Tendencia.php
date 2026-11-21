<?php

namespace App\Domain;

use App\Models\BalanceCorrida;
use Illuminate\Support\Facades\DB;

/**
 * Adjustment history per flow over the last saved balances of a process: how far each run moved TMS and each
 * grade from the measurement, and whether one meter is consistently corrected the same way (recurring bias).
 */
final class Tendencia
{
    public const BALANCES = 8;
    /** Recurring bias: same sign in at least MIN_MISMO_SIGNO of the last VENTANA balances, each beyond half its tolerance. */
    public const VENTANA = 4;
    public const MIN_MISMO_SIGNO = 3;
    public const FRACCION_TOL = 0.5;
    /** Tolerance used when a variable has none (diagnostico.py's TOL_DEFECTO_PCT). */
    public const TOL_DEFECTO_PCT = 5.0;

    /**
     * Keyed by "nombre|tag" (tag empty when the flow has none).
     *
     * @return array<string, array{flujo: string, tag: ?string, serie: list<array{balance_id:int, periodo:?string, ajuste_pct:float}>, sesgo_recurrente:bool, signo:?int, leyes: list<array>}>
     */
    public static function deProceso(int $procesoId): array
    {
        // Last saved balances of the process, each with its newest session (a balance points at its latest one).
        $balances = DB::table('balances as b')
            ->join('datos_entradas as d', 'd.balance_id', '=', 'b.id')
            ->where('d.proceso_id', $procesoId)
            ->groupBy('b.id', 'b.periodo_inicio', 'b.periodo_fin', 'b.created_at')
            ->orderByRaw('COALESCE(b.periodo_fin, b.created_at) DESC')
            ->orderByDesc('b.id')
            ->limit(self::BALANCES)
            ->get(['b.id', 'b.periodo_inicio', 'b.periodo_fin', DB::raw('MAX(d.id) as sesion_id')]);
        if ($balances->isEmpty()) {
            return [];
        }
        $sesiones = $balances->pluck('sesion_id')->map(fn ($id) => (int) $id)->all();

        // The newest run of each session; v1 balances have no run history, but they were run before saving,
        // so their stored datos_entrada is that run.
        $ultimas = DB::table('balance_corridas')->where('tipo', BalanceCorrida::TIPO_CORRIDA)
            ->whereIn('datos_entrada_id', $sesiones)->groupBy('datos_entrada_id')
            ->get([DB::raw('MAX(id) as id'), 'datos_entrada_id'])->pluck('id', 'datos_entrada_id');
        $snapshots = DB::table('balance_corridas')->whereIn('id', $ultimas->values()->all())->pluck('snapshot', 'datos_entrada_id');
        $sinCorrida = array_values(array_diff($sesiones, $ultimas->keys()->map(fn ($k) => (int) $k)->all()));
        if ($sinCorrida) {
            $snapshots = $snapshots->union(DB::table('datos_entradas')->whereIn('id', $sinCorrida)->pluck('datos_entrada', 'id'));
        }

        // Oldest first, as a series is drawn.
        $series = [];
        $tags = $nombres = [];
        foreach ($balances->reverse() as $b) {
            $d = SesionMapper::decode($snapshots[$b->sesion_id] ?? null);
            $periodo = $b->periodo_fin ? substr((string) $b->periodo_fin, 0, 10) : null;
            // Flows are told apart by name and tag; a key repeated within one balance is ambiguous and skipped.
            $claves = array_map(fn ($i) => $d['flujos'][$i].'|'.($d['tags'][$i] ?? ''), array_keys($d['flujos'] ?? []));
            $repetidas = array_keys(array_filter(array_count_values($claves), fn ($n) => $n > 1));
            foreach ($d['flujos'] ?? [] as $i => $nombre) {
                $clave = $claves[$i];
                if (in_array($clave, $repetidas, true)) {
                    continue;
                }
                $nombres[$clave] = (string) $nombre;
                $tags[$clave] = isset($d['tags'][$i]) ? (string) $d['tags'][$i] : null;
                $m = $d['mediciones'][$i] ?? [];
                $r = $d['restricciones'][$i] ?? [];
                for ($c = 0; $c + 1 < count($m); $c += 2) {
                    $med = abs(SesionMapper::num($m[$c]));
                    if ($med == 0) {
                        continue;
                    }
                    // Magnitudes: a negative TMS only marks a stock's direction (same rule as the UI).
                    $ajuste = (abs(SesionMapper::num($m[$c + 1])) - $med) / $med * 100;
                    $tol = SesionMapper::num($r[$ajuste >= 0 ? $c + 1 : $c] ?? 0);
                    $tol = $tol > 0 ? $tol : self::TOL_DEFECTO_PCT; // same default as Flask and the client
                    $series[$clave][$c / 2][] = ['balance_id' => (int) $b->id, 'periodo' => $periodo, 'ajuste_pct' => round($ajuste, 4), 'tol' => $tol];
                }
            }
        }

        $out = [];
        foreach ($series as $clave => $vars) {
            $tms = self::resumen($vars[0] ?? []);
            $leyes = [];
            for ($k = 1; $k <= max(array_keys($vars)); $k++) {
                $leyes[] = self::resumen($vars[$k] ?? []);
            }
            $out[$clave] = ['flujo' => $nombres[$clave], 'tag' => $tags[$clave]] + $tms + ['leyes' => $leyes];
        }

        return $out;
    }

    private static function resumen(array $puntos): array
    {
        $ultimos = array_slice($puntos, -self::VENTANA);
        $signo = null;
        foreach ([1, -1] as $sg) {
            $n = count(array_filter($ultimos, fn ($p) => $p['ajuste_pct'] * $sg > 0
                && abs($p['ajuste_pct']) > self::FRACCION_TOL * $p['tol']));
            if ($n >= self::MIN_MISMO_SIGNO) {
                $signo = $sg;
            }
        }

        return [
            'serie' => array_map(fn ($p) => array_diff_key($p, ['tol' => 0]), $puntos),
            'sesgo_recurrente' => $signo !== null,
            'signo' => $signo,
        ];
    }
}
