<?php

namespace Tests\Unit;

use App\Domain\Asistente;
use PHPUnit\Framework\TestCase;

class AsistenteContextoTest extends TestCase
{
    private function v(float $med, float $rec, float $tol): array
    {
        return ['medido' => $med, 'reconciliado' => $rec, 'tol_inf' => $tol, 'tol_sup' => $tol, 'desv_inf' => 0, 'desv_sup' => 0];
    }

    public function testContextRanksFlowsAndCarriesBiasAndWideTolerances(): void
    {
        $flujos = [];
        for ($i = 0; $i < 35; $i++) {
            $flujos[] = ['idx' => $i, 'nombre' => "F{$i}", 'tag' => null, 'tms' => $this->v(100, 100 + $i, $i === 3 ? 40 : 10), 'leyes' => [$this->v(60, 60, 2)]];
        }
        $dto = [
            'proceso' => ['nombre' => 'Pleito', 'valle' => ['nombre' => 'Elqui']], 'componentes' => ['Fe T'], 'corrida' => true, 'solver' => ['ok' => true],
            'flujos' => $flujos, 'nodos' => [['idx' => 0, 'nombre' => 'N0']], 'matriz' => [array_fill(0, 35, 1)],
        ];
        $serie = fn ($a) => array_map(fn ($x) => ['balance_id' => 1, 'periodo' => null, 'ajuste_pct' => $x], $a);
        $diag = [
            'diagnostico' => ['global' => ['gamma' => 9, 'gl' => 1, 'critico' => 3.84, 'pasa' => false], 'global_final' => [], 'nodos' => [['nodo' => 0, 'residuo' => 5, 'z' => 3.1, 'sospechoso' => true]],
                'sospechosos' => [], 'no_redundantes' => ['F7'], 'excluidos' => [], 'por_componente' => []],
            'tendencia' => ['F2|' => ['flujo' => 'F2', 'tag' => null, 'serie' => $serie([8, 8, 9, 7]), 'sesgo_recurrente' => true, 'signo' => 1, 'leyes' => [['serie' => [], 'sesgo_recurrente' => false, 'signo' => null]]]],
        ];

        $c = json_decode(Asistente::contexto($dto, $diag), true);

        $this->assertCount(Asistente::TOP_FLUJOS, $c['flujos_mayor_ajuste']);
        $this->assertSame('F34', $c['flujos_mayor_ajuste'][0]['flujo']);
        $this->assertEquals(34.0, $c['flujos_mayor_ajuste'][0]['tms']['ajuste_pct']);
        $this->assertSame('N0', $c['nodos'][0]['nodo']);
        $this->assertSame([['flujo' => 'F2', 'tag' => null, 'variable' => 'tms', 'nombre_variable' => 'TMS', 'direccion' => 'el balance lo corrige hacia arriba', 'ultimos_ajustes_pct' => [8.0, 8.0, 9.0, 7.0]]], $c['sesgo_recurrente']);
        $this->assertSame(['F3'], array_column($c['tolerancias_amplias']['flujos'], 'flujo'));
        $this->assertSame(['F7'], $c['sin_redundancia']);
        $this->assertEqualsWithDelta(1.959964, Asistente::sidakZ(1), 1e-5);
    }
}
