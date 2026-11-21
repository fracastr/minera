<?php

namespace App\Domain;

/**
 * Reconciliation quality counts of one session: the minimal server-side port of web/src/domain/reconciliationQuality.ts
 * (same thresholds and rules; keep both in step).
 */
final class Calidad
{
    public const NEAR_LIMIT_PCT = 80;
    public const NODE_CLOSURE_TOLERANCE_PCT = 0.1;

    /** 'ok' | 'limite' | 'fuera' | 'sin-tolerancia' */
    public static function estadoDeUso(?float $usoPct): string
    {
        return match (true) {
            $usoPct === null => 'sin-tolerancia',
            $usoPct > 100.5 => 'fuera',
            $usoPct >= self::NEAR_LIMIT_PCT => 'limite',
            default => 'ok',
        };
    }

    /** Magnitudes, not signed values: the solver bounds |x| and a negative TMS only marks a stock's direction. */
    public static function estado(array $v, int $jerarquia): ?string
    {
        $med = abs($v['medido']);
        if (!$med) {
            return null;
        }
        $ajustePct = (abs($v['reconciliado']) - $med) / $med * 100;
        $tol = $ajustePct >= 0 ? $v['tol_sup'] : $v['tol_inf'];
        $uso = $jerarquia === 1 && $tol > 0 ? abs($ajustePct) / $tol * 100 : null;

        return self::estadoDeUso($uso);
    }

    /**
     * @param  array  $dto  SesionMapper::toDto() output
     * @return array{fuera:int, limite:int, nodos_sin_cerrar:int}
     */
    public static function resumen(array $dto): array
    {
        $fuera = $limite = 0;
        foreach ($dto['flujos'] as $f) {
            foreach ([$f['tms'], ...$f['leyes']] as $v) {
                $e = self::estado($v, $f['jerarquia']);
                $fuera += $e === 'fuera' ? 1 : 0;
                $limite += $e === 'limite' ? 1 : 0;
            }
        }

        // Node throughput = (inputs + outputs) / 2, independent of the matrix sign convention.
        $sinCerrar = 0;
        foreach ($dto['nodos'] as $n) {
            $throughput = 0.0;
            foreach ($dto['matriz'][$n['idx']] ?? [] as $j => $c) {
                $throughput += abs($c) * abs($dto['flujos'][$j]['tms']['reconciliado'] ?? 0);
            }
            $throughput /= 2;
            $residual = abs($n['residuo_tms']);
            $cierra = $throughput > 0 ? $residual / $throughput * 100 <= self::NODE_CLOSURE_TOLERANCE_PCT : $residual < 1;
            $sinCerrar += $cierra ? 0 : 1;
        }

        return ['fuera' => $fuera, 'limite' => $limite, 'nodos_sin_cerrar' => $sinCerrar];
    }
}
