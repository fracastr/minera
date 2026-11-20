<?php

namespace Tests\Feature;

use App\Models\BalanceCorrida;
use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceCorridasTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private int $procesoId;

    protected function setUp(): void
    {
        parent::setUp();

        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $valle = DB::table('valles')->insertGetId(['nombre' => 'V', 'tipo' => 't', 'empresa_id' => $empresa]);
        $this->procesoId = DB::table('procesos')->insertGetId([
            'nombre' => 'P', 'tipo' => 't', 'valle_id' => $valle, 'componentes' => '{"data":["FeT"]}',
        ]);
        $this->operator = User::factory()->create(['role' => 'operator']);
    }

    private function datos(float $tms, ?int $procesoId = null): Datos_entrada
    {
        return Datos_entrada::create([
            'proceso_id' => $procesoId ?? $this->procesoId,
            'user_id' => $this->operator->id,
            'datos_entrada' => json_encode([
                'mediciones' => [[$tms, $tms, 60, 60]],
                'flujos' => ['F1'],
                'restricciones' => [[1, 1, 1, 1]],
                'jerarquia' => [1],
                'desviaciones' => [[0, 0, 0, 0]],
                'nodos' => ['N1'],
                'matriz' => [[1]],
                'nodos_data' => [[$tms, 5]],
                'inventarios' => [], 'tmh_ini' => [], 'tmh_fin' => [], 'tmh_delta' => [],
                'humedad_inventario' => [], 'tms_ini' => [], 'tms_fin' => [], 'tms_delta' => [],
                'componentes_inventario' => [],
            ]),
        ]);
    }

    public function testRestoreBringsBackAnEarlierVersionAndIsRecorded(): void
    {
        $session = $this->datos(100);
        $import = BalanceCorrida::record($session, $this->operator, BalanceCorrida::TIPO_IMPORTACION);
        $session->datos_entrada = $this->datos(250)->datos_entrada;
        $session->save();
        BalanceCorrida::record($session, $this->operator, BalanceCorrida::TIPO_CORRIDA);

        $this->actingAs($this->operator)
            ->postJson("/api/balances/corridas/{$import->id}/restaurar", ['datos_entrada_id' => $session->id])
            ->assertOk()
            ->assertJsonPath('balances_table.0.TMS medido', '100');

        $this->assertSame(100, json_decode($session->fresh()->datos_entrada, true)['mediciones'][0][0]);

        $tipos = $this->actingAs($this->operator)->getJson("/api/balances/corridas/{$session->id}")
            ->assertOk()->json('corridas.*.tipo');
        $this->assertSame(['restauracion', 'corrida', 'importacion'], $tipos);
    }

    public function testCloneKeepsHistoryAndCanRestoreFromIt(): void
    {
        $session = $this->datos(100);
        $import = BalanceCorrida::record($session, $this->operator, BalanceCorrida::TIPO_IMPORTACION);

        $cloneId = $this->actingAs($this->operator)
            ->postJson('/api/balances/clone_session', ['datos_entrada_id' => $session->id])
            ->assertOk()->json('datos_entrada_id');

        $this->assertSame(1, BalanceCorrida::where('datos_entrada_id', $cloneId)->count());
        $this->actingAs($this->operator)
            ->postJson("/api/balances/corridas/{$import->id}/restaurar", ['datos_entrada_id' => $cloneId])
            ->assertOk();
    }

    public function testRestoreRejectsAnotherProcessAndReadOnlyUsers(): void
    {
        $otroProceso = DB::table('procesos')->insertGetId([
            'nombre' => 'Q', 'tipo' => 't', 'valle_id' => DB::table('valles')->value('id'), 'componentes' => '{"data":["FeT"]}',
        ]);
        $corrida = BalanceCorrida::record($this->datos(100), $this->operator, BalanceCorrida::TIPO_IMPORTACION);
        $other = $this->datos(100, $otroProceso);

        $this->actingAs($this->operator)
            ->postJson("/api/balances/corridas/{$corrida->id}/restaurar", ['datos_entrada_id' => $other->id])
            ->assertStatus(422);

        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)
            ->postJson("/api/balances/corridas/{$corrida->id}/restaurar", ['datos_entrada_id' => $corrida->datos_entrada_id])
            ->assertForbidden();
    }

    public function testSaveRequiresAValidPeriodAndListsIt(): void
    {
        $session = $this->datos(100);
        $payload = [
            'datos_entrada_id' => $session->id,
            'proceso_id' => $this->procesoId,
            'nombre_balance' => 'Noviembre 1Q',
            'tipo' => 'quincenal',
            'periodo_inicio' => '2026-11-15',
            'periodo_fin' => '2026-11-01',
        ];

        $this->actingAs($this->operator)->postJson('/api/balances/save_balance', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('periodo_fin');

        $payload['periodo_inicio'] = '2026-11-01';
        $payload['periodo_fin'] = '2026-11-15';
        $this->actingAs($this->operator)->postJson('/api/balances/save_balance', $payload)->assertOk();

        $this->actingAs($this->operator)->getJson('/api/balances/get_listado')
            ->assertOk()
            ->assertJsonPath('listado.0.tipo', 'quincenal')
            ->assertJsonPath('listado.0.periodo_inicio', '2026-11-01')
            ->assertJsonPath('listado.0.periodo_fin', '2026-11-15');
    }
}
