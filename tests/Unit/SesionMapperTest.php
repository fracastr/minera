<?php

namespace Tests\Unit;

use App\Domain\SesionMapper;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SesionMapperTest extends TestCase
{
    /** Two flows into one node; flow 1 is a stock delta drawn the other way (negative TMS). */
    private function datos(): array
    {
        return [
            'flujos' => ['Alimentación', 'Delta stock'],
            'tags' => ['T1', 'T2'],
            'descripcion' => ['A', 'B'],
            'nodos' => ['Planta'],
            'matriz' => [[1, -1]],
            'mediciones' => [[100.4, 101.25, 60, 59.5], [-100, -101.25, 60, 60.5]],
            'restricciones' => [[5, 5, 2, 2], [10, 10, 3, 3]],
            'desviaciones' => [[0, 0.85, 0.83, 0], [0, 1.25, 0, 0.83]],
            'jerarquia' => [1, 2],
            'nodos_data' => [[0.5, 0.01]],
            'indices' => [[1, 2]],
            'indices_stock' => [[1, 2]],
            'maxim' => [[20, 10], [20, 10]],
            'inventarios' => ['Pila'],
            'tmh_ini' => [110], 'tmh_fin' => [220], 'tmh_delta' => [110],
            'humedad_inventario' => [0], 'tms_ini' => [110], 'tms_fin' => [220], 'tms_delta' => [110],
            'componentes_inventario' => [[55]],
        ];
    }

    public function testDtoExposesFullPrecisionNumbersAndTheStoredSign(): void
    {
        $dto = SesionMapper::toDto($this->datos(), ['FeT']);

        $this->assertSame(['FeT'], $dto['componentes']);
        $f0 = $dto['flujos'][0];
        $this->assertSame(100.4, $f0['tms']['medido']);
        $this->assertSame(101.25, $f0['tms']['reconciliado']);
        $this->assertSame(0.85, $f0['tms']['desv_sup']);
        $this->assertSame(['medido' => 60.0, 'reconciliado' => 59.5, 'tol_inf' => 2.0, 'tol_sup' => 2.0, 'desv_inf' => 0.83, 'desv_sup' => 0.0], $f0['leyes'][0]);
        $this->assertFalse($f0['stock']);
        $this->assertSame('T1', $f0['tag']);

        $f1 = $dto['flujos'][1];
        $this->assertTrue($f1['stock']);
        $this->assertSame(-100.0, $f1['tms']['medido']);
        $this->assertSame(2, $f1['jerarquia']);

        $this->assertSame([['idx' => 0, 'nombre' => 'Planta', 'residuo_tms' => 0.5, 'residuo_finos' => [0.01]]], $dto['nodos']);
        $this->assertSame([[1, -1]], $dto['matriz']);
        $this->assertSame(55.0, $dto['inventarios'][0]['leyes'][0]);
    }

    public function testEditsWriteBackIntoTheSameKeysAndKeepReconciledValues(): void
    {
        $d = $this->datos();
        $out = SesionMapper::applyEdits($d, [
            'flujos' => [
                ['idx' => 1, 'tms_medido' => -90.123456789, 'leyes_medido' => [61.5], 'tms_tol_inf' => 4, 'tms_tol_sup' => 6, 'leyes_tol' => [[1, 2]], 'jerarquia' => 1],
            ],
            'inventarios' => [['idx' => 0, 'humedad' => 10, 'tms_ini' => 90, 'tms_fin' => 180, 'leyes' => [57]]],
        ]);

        $this->assertSame(array_keys($d), array_keys($out));
        $this->assertSame([-90.123456789, -101.25, 61.5, 60.5], $out['mediciones'][1]);
        $this->assertSame($d['mediciones'][0], $out['mediciones'][0]);
        $this->assertSame([4.0, 6.0, 1.0, 2.0], $out['restricciones'][1]);
        $this->assertSame([1, 1], $out['jerarquia']);

        $this->assertSame(90.0, $out['tms_delta'][0]);
        $this->assertEqualsWithDelta(100.0, $out['tmh_ini'][0], 1e-9);
        $this->assertEqualsWithDelta(200.0, $out['tmh_fin'][0], 1e-9);
        $this->assertEqualsWithDelta(100.0, $out['tmh_delta'][0], 1e-9);
        $this->assertSame(10.0, $out['humedad_inventario'][0]);
        $this->assertSame([57.0], $out['componentes_inventario'][0]);
    }

    public function testRowsWithoutRunExtrasGetDeviationsAndResidualsLikeTheSolver(): void
    {
        $d = $this->datos();
        unset($d['desviaciones'], $d['nodos_data']);
        $dto = SesionMapper::toDto($d, ['FeT']);

        // (101.25 - 100.4) / 100.4 → moved up 0.8466 %
        $this->assertEqualsWithDelta(0.8466, $dto['flujos'][0]['tms']['desv_sup'], 1e-4);
        $this->assertSame(0.0, $dto['flujos'][0]['tms']['desv_inf']);
        // |101.25 - 101.25| = 0; finos |101.25·0.595 − 101.25·0.605|
        $this->assertEqualsWithDelta(0.0, $dto['nodos'][0]['residuo_tms'], 1e-9);
        $this->assertEqualsWithDelta(1.0125, $dto['nodos'][0]['residuo_finos'][0], 1e-9);
    }

    public function testDecodeAcceptsDoubleEncodedRowsAndLegacyNumberStrings(): void
    {
        $this->assertSame(['a' => 1], SesionMapper::decode(json_encode(json_encode(['a' => 1]))));
        $this->assertSame([], SesionMapper::decode('not json'));
        $this->assertSame(1234.56, SesionMapper::num('1.234,56'));
        $this->assertSame(0.0, SesionMapper::num(null));
    }

    public function testEditRulesAreSizedToTheSession(): void
    {
        $rules = SesionMapper::editRules($this->datos());
        $ok = ['idx' => 0, 'tms_medido' => 1, 'leyes_medido' => [1], 'tms_tol_inf' => 1, 'tms_tol_sup' => 1, 'leyes_tol' => [[1, 1]], 'jerarquia' => 1];

        $this->assertTrue(Validator::make(['flujos' => [$ok]], $rules)->passes());
        $this->assertTrue(Validator::make(['flujos' => [[...$ok, 'idx' => 2]]], $rules)->fails());
        $this->assertTrue(Validator::make(['flujos' => [[...$ok, 'leyes_medido' => [1, 2]]]], $rules)->fails());
        $this->assertTrue(Validator::make(['flujos' => [[...$ok, 'tms_medido' => 'abc']]], $rules)->fails());
        $this->assertTrue(Validator::make(['inventarios' => [['idx' => 0, 'humedad' => 100, 'tms_ini' => 1, 'tms_fin' => 1, 'leyes' => [1]]]], $rules)->fails());
    }
}
