<?php

namespace Tests\Unit;

use App\Domain\Calidad;
use PHPUnit\Framework\TestCase;

/** Same fixtures as web/src/domain/__tests__/domain.test.ts (reconciliationQuality). */
class CalidadTest extends TestCase
{
    private static function v(float $medido, float $reconciliado, float $tol = 5): array
    {
        return ['medido' => $medido, 'reconciliado' => $reconciliado, 'tol_inf' => $tol, 'tol_sup' => $tol];
    }

    private static function dto(float $residuo = 0.0004): array
    {
        $flujo = fn (int $idx, array $tms, array $ley, int $jerarquia = 1) => ['idx' => $idx, 'jerarquia' => $jerarquia, 'tms' => $tms, 'leyes' => [$ley]];

        return [
            'flujos' => [
                $flujo(0, self::v(100, 104), self::v(60, 60.5)),
                $flujo(1, self::v(80, 80.9), self::v(65, 65)),
                $flujo(2, self::v(-25, -23.1, 10), self::v(50, 50), 2),
            ],
            'nodos' => [['idx' => 0, 'residuo_tms' => $residuo]],
            'matriz' => [[1, -1, -1]],
        ];
    }

    public function testClassifiesToleranceUse(): void
    {
        $this->assertSame('sin-tolerancia', Calidad::estadoDeUso(null));
        $this->assertSame('ok', Calidad::estadoDeUso(50));
        $this->assertSame('limite', Calidad::estadoDeUso(80));
        $this->assertSame('limite', Calidad::estadoDeUso(100.4));
        $this->assertSame('fuera', Calidad::estadoDeUso(101));
    }

    public function testMeasuresAdjustmentsOnMagnitudes(): void
    {
        $this->assertSame('limite', Calidad::estado(self::v(100, 104), 1));
        $this->assertSame('fuera', Calidad::estado(self::v(-100, -106), 1));
        $this->assertSame('sin-tolerancia', Calidad::estado(self::v(-100, -106), 2));
        $this->assertNull(Calidad::estado(self::v(0, 3), 1));
    }

    public function testSummarizesASession(): void
    {
        $this->assertSame(['fuera' => 0, 'limite' => 1, 'nodos_sin_cerrar' => 0], Calidad::resumen(self::dto()));
    }

    public function testFlagsANodeWhoseResidualExceedsTheClosureTolerance(): void
    {
        $this->assertSame(1, Calidad::resumen(self::dto(5))['nodos_sin_cerrar']);
    }
}
