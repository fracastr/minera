<?php

namespace Tests\Unit;

use App\Http\Controllers\BalancesController;
use PHPUnit\Framework\TestCase;

class ApplyInventariosTableTest extends TestCase
{
    public function testEditedGridRowsAreWrittenBackIntoDatosEntrada(): void
    {
        $datos = [
            'tmh_ini' => [100, 200],
            'humedad_inventario' => [5, 6],
            'tms_ini' => [95, 188],
            'componentes_inventario' => [[60.0, 30.0], [61.0, 31.0]],
            'mediciones' => [[1, 2]],
        ];
        $grid = [
            ['Inventario' => 'Stock A', 'TMH INI' => '1,100', 'Humedad [%]' => '7.5', 'FeT [%]' => '62.5'],
            ['Inventario' => 'Stock B', 'TMS INI' => 190],
        ];

        $out = BalancesController::applyInventariosTable($datos, $grid, ['FeT', 'FeMag']);

        $this->assertSame(1100.0, $out['tmh_ini'][0]);
        $this->assertSame(7.5, $out['humedad_inventario'][0]);
        $this->assertSame(62.5, $out['componentes_inventario'][0][0]);
        $this->assertSame(30.0, $out['componentes_inventario'][0][1]);
        $this->assertSame(190.0, $out['tms_ini'][1]);
        $this->assertSame(200, $out['tmh_ini'][1]);
        $this->assertSame([[1, 2]], $out['mediciones']);
        $this->assertArrayNotHasKey('tmh_fin', $out);
    }
}
