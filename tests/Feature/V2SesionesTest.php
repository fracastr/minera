<?php

namespace Tests\Feature;

use App\Models\BalanceCorrida;
use App\Models\BalanceEvent;
use App\Models\Balances;
use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class V2SesionesTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private int $valleId;
    private int $procesoId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.flask.url' => 'http://solver.test']);

        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $this->valleId = DB::table('valles')->insertGetId(['nombre' => 'Elqui', 'tipo' => 't', 'empresa_id' => $empresa]);
        $this->procesoId = DB::table('procesos')->insertGetId([
            'nombre' => 'Pleito', 'tipo' => 't', 'valle_id' => $this->valleId, 'componentes' => '{"data":["FeT"]}',
        ]);
        $this->operator = User::factory()->create(['role' => 'operator']);
    }

    private function datos(float $tms = 100): array
    {
        return [
            'flujos' => ['F1', 'Delta'], 'tags' => ['T1', 'T2'], 'descripcion' => ['', ''],
            'nodos' => ['N1'], 'matriz' => [[1, -1]],
            'mediciones' => [[$tms, $tms, 60, 60], [-$tms, -$tms, 60, 60]],
            'restricciones' => [[5, 5, 2, 2], [5, 5, 2, 2]], 'jerarquia' => [1, 1],
            'desviaciones' => [[0, 0, 0, 0], [0, 0, 0, 0]], 'nodos_data' => [[0, 0]],
            'indices' => [[1, 2]], 'indices_stock' => [[1, 2]], 'maxim' => [[20, 10], [20, 10]],
            'inventarios' => ['Pila'], 'tmh_ini' => [10], 'tmh_fin' => [20], 'tmh_delta' => [10],
            'humedad_inventario' => [0], 'tms_ini' => [10], 'tms_fin' => [20], 'tms_delta' => [10],
            'componentes_inventario' => [[55]],
        ];
    }

    private function sesion(array $attrs = []): Datos_entrada
    {
        return Datos_entrada::create($attrs + [
            'proceso_id' => $this->procesoId,
            'valle_id' => $this->valleId,
            'user_id' => $this->operator->id,
            'datos_entrada' => json_encode($this->datos()),
        ]);
    }

    /** The solver answers with a map of JSON-encoded strings. */
    private function solverResponse(array $datos, array $finales): array
    {
        return [
            'datos_entrada' => json_encode($datos),
            'balances_finales' => json_encode($finales),
            'desviaciones' => json_encode([[0, 1.5, 0, 0], [0, 0, 0, 0]]),
            'nodos_data' => json_encode([[0.25, 0.01]]),
        ];
    }

    private function edits(float $tms): array
    {
        return ['flujos' => [
            ['idx' => 0, 'tms_medido' => $tms, 'leyes_medido' => [61.25], 'tms_tol_inf' => 3, 'tms_tol_sup' => 4, 'leyes_tol' => [[1, 1]], 'jerarquia' => 1],
        ]];
    }

    public function testImportStoresTheFileCallsTheSolverAndRecordsTheSession(): void
    {
        Storage::fake('local');
        Http::fake(['solver.test/get_balance' => Http::response($this->solverResponse($this->datos(), []))]);
        $file = UploadedFile::fake()->createWithContent('Carga Pleito.xlsx', file_get_contents(public_path('Export/Exportar_Elqui_Pleito.xlsx')));

        $id = $this->actingAs($this->operator)
            ->post('/api/v2/sesiones', ['proceso_id' => $this->procesoId, 'file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');

        $sesion = Datos_entrada::findOrFail($id);
        $this->assertMatchesRegularExpression('/^Carga_Pleito_\d{14}_'.$this->operator->id.'\.xlsx$/', $sesion->file_path);
        Storage::disk('local')->assertExists('public/'.$sesion->file_path);
        Http::assertSent(fn (HttpRequest $r) => $r['nombre_proceso'] === 'Pleito'
            && $r['path_name'] === Storage::disk('local')->path('public/'.$sesion->file_path));
        $this->assertSame([[0.25, 0.01]], json_decode($sesion->datos_entrada, true)['nodos_data']);
        $this->assertSame(['importacion'], BalanceCorrida::where('datos_entrada_id', $id)->pluck('tipo')->all());
        $this->assertTrue(BalanceEvent::where('datos_entrada_id', $id)->where('event_type', BalanceEvent::TYPE_IMPORTED)->exists());

        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$id}")
            ->assertOk()
            ->assertJsonPath('proceso.valle.nombre', 'Elqui')
            ->assertJsonPath('corrida', false)
            ->assertJsonPath('flujos.1.stock', true)
            ->assertJsonPath('flujos.1.tms.medido', -100);
    }

    public function testImportValidatesProcessAndFile(): void
    {
        $this->actingAs($this->operator)
            ->post('/api/v2/sesiones', ['proceso_id' => 999, 'file' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['proceso_id', 'file']);
    }

    public function testRunWritesEditsBeforeCallingTheSolverAndStoresTheResult(): void
    {
        $sesion = $this->sesion();
        $seenBySolver = null;
        Http::fake(function (HttpRequest $r) use (&$seenBySolver, $sesion) {
            $seenBySolver = json_decode(Datos_entrada::find($sesion->id)->datos_entrada, true);

            return Http::response($this->solverResponse($seenBySolver, [[90.5, 98.123456, 61.25, 60.5], [-100, -98.123456, 60, 60.1]]));
        });

        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/correr", $this->edits(90.5))
            ->assertOk()
            ->assertJsonPath('corrida', true)
            ->assertJsonPath('flujos.0.tms.medido', 90.5)
            ->assertJsonPath('flujos.0.tms.reconciliado', 98.123456)
            ->assertJsonPath('flujos.0.tms.desv_sup', 1.5)
            ->assertJsonPath('nodos.0.residuo_tms', 0.25);

        $this->assertSame([90.5, 100, 61.25, 60], $seenBySolver['mediciones'][0]);
        $this->assertSame([3, 4, 1, 1], $seenBySolver['restricciones'][0]);
        $this->assertSame(['corrida'], BalanceCorrida::where('datos_entrada_id', $sesion->id)->pluck('tipo')->all());
        $this->assertTrue(BalanceEvent::where('datos_entrada_id', $sesion->id)->where('event_type', BalanceEvent::TYPE_CORRER)->exists());
    }

    public function testRunReportsAnUnreachableSolverReadablyAndKeepsTheEdits(): void
    {
        $sesion = $this->sesion();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/correr", $this->edits(77))
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'El solver no respondió'));

        $this->assertSame(77, json_decode($sesion->fresh()->datos_entrada, true)['mediciones'][0][0]);
        $this->assertSame(0, BalanceCorrida::where('datos_entrada_id', $sesion->id)->count());
    }

    public function testSaveDataPersistsEditsAndValidatesThem(): void
    {
        $sesion = $this->sesion();

        $this->actingAs($this->operator)->putJson("/api/v2/sesiones/{$sesion->id}/datos", [
            ...$this->edits(123.456789),
            'inventarios' => [['idx' => 0, 'humedad' => 20, 'tms_ini' => 8, 'tms_fin' => 16, 'leyes' => [50]]],
        ])->assertOk()
            ->assertJsonPath('flujos.0.tms.medido', 123.456789)
            ->assertJsonPath('inventarios.0.tms_delta', 8)
            ->assertJsonPath('inventarios.0.tmh_fin', 20);

        $this->actingAs($this->operator)->putJson("/api/v2/sesiones/{$sesion->id}/datos", ['flujos' => [
            ['idx' => 5, 'tms_medido' => 'x', 'leyes_medido' => [1, 2], 'tms_tol_inf' => -1, 'tms_tol_sup' => 1, 'leyes_tol' => [[1, 1]], 'jerarquia' => 3],
        ]])->assertStatus(422)->assertJsonValidationErrors([
            'flujos.0.idx', 'flujos.0.tms_medido', 'flujos.0.leyes_medido', 'flujos.0.tms_tol_inf', 'flujos.0.jerarquia',
        ]);
    }

    public function testRestoreRejectsAVersionFromAnotherProcess(): void
    {
        $otro = DB::table('procesos')->insertGetId(['nombre' => 'Q', 'tipo' => 't', 'valle_id' => $this->valleId, 'componentes' => '{"data":["FeT"]}']);
        $ajena = BalanceCorrida::record($this->sesion(['proceso_id' => $otro]), $this->operator, BalanceCorrida::TIPO_IMPORTACION);
        $sesion = $this->sesion();
        $import = BalanceCorrida::record($sesion, $this->operator, BalanceCorrida::TIPO_IMPORTACION);
        $sesion->update(['datos_entrada' => json_encode($this->datos(250))]);

        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/corridas/{$ajena->id}/restaurar")
            ->assertStatus(422)->assertJsonPath('message', 'La versión pertenece a otro proceso.');

        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/corridas/{$import->id}/restaurar")
            ->assertOk()->assertJsonPath('flujos.0.tms.medido', 100);

        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$sesion->id}/corridas")
            ->assertOk()
            ->assertJsonPath('corridas.0.tipo', 'restauracion')
            ->assertJsonPath('corridas.0.actual', true)
            ->assertJsonPath('corridas.1.actual', false);
    }

    public function testSaveCreatesThenOverwritesAndReopens(): void
    {
        $sesion = $this->sesion();
        $body = ['nombre' => 'Nov 1Q', 'tipo' => 'quincenal', 'periodo_inicio' => '2026-11-01', 'periodo_fin' => '2026-11-15', 'sobrescribir' => false];

        $balanceId = $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/guardar", $body)
            ->assertOk()->json('balance_id');
        $this->assertSame($balanceId, $sesion->fresh()->balance_id);

        // Saving a saved session as new would orphan its balance.
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/guardar", $body)->assertStatus(422);

        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/guardar", ['nombre' => 'Nov mensual', 'tipo' => 'mensual', 'periodo_fin' => '2026-11-30', 'sobrescribir' => true] + $body)
            ->assertOk()->assertJsonPath('balance_id', $balanceId);
        $this->assertSame(['Nov mensual', 'mensual', '2026-11-30'], [
            Balances::find($balanceId)->nombre, Balances::find($balanceId)->tipo, Balances::find($balanceId)->periodo_fin->format('Y-m-d'),
        ]);
        $this->assertSame(1, Balances::count());

        $this->actingAs($this->operator)->getJson("/api/v2/balances/{$balanceId}/sesion")->assertOk()->assertJsonPath('sesion_id', $sesion->id);
        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$sesion->id}")->assertJsonPath('balance.nombre', 'Nov mensual');

        // "Guardar como nuevo" on a reopened balance: clone, then save the clone.
        $cloneId = $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/clonar")->assertCreated()->json('id');
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$cloneId}/guardar", $body)->assertOk();
        $this->assertSame(2, Balances::count());
        $this->assertSame($balanceId, $sesion->fresh()->balance_id);
    }

    public function testSaveValidatesPeriodAndProcess(): void
    {
        $sesion = $this->sesion();
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$sesion->id}/guardar", [
            'nombre' => '', 'tipo' => 'anual', 'periodo_inicio' => '2026-11-15', 'periodo_fin' => '2026-11-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['nombre', 'tipo', 'periodo_fin', 'sobrescribir']);

        $huerfana = $this->sesion(['proceso_id' => null]);
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$huerfana->id}/guardar", [
            'nombre' => 'X', 'tipo' => 'mensual', 'periodo_inicio' => '2026-11-01', 'periodo_fin' => '2026-11-30', 'sobrescribir' => false,
        ])->assertStatus(422)->assertJsonPath('message', 'La sesión no tiene un proceso válido.');
    }

    public function testViewerCanReadButNotWrite(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $sesion = $this->sesion();
        Http::fake();
        $this->getJson('/api/v2/catalogo')->assertUnauthorized();

        $this->actingAs($viewer)->getJson("/api/v2/sesiones/{$sesion->id}")->assertOk();
        $this->actingAs($viewer)->getJson('/api/v2/balances')->assertOk();
        $this->actingAs($viewer)->getJson('/api/v2/catalogo')->assertOk();

        foreach ([
            ['putJson', "/api/v2/sesiones/{$sesion->id}/datos"],
            ['postJson', "/api/v2/sesiones/{$sesion->id}/correr"],
            ['postJson', "/api/v2/sesiones/{$sesion->id}/clonar"],
            ['postJson', "/api/v2/sesiones/{$sesion->id}/guardar"],
            ['postJson', "/api/v2/sesiones/{$sesion->id}/corridas/1/restaurar"],
            ['postJson', '/api/v2/sesiones'],
        ] as [$method, $url]) {
            $this->actingAs($viewer)->{$method}($url)->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function testMissingSessionIs404(): void
    {
        $this->actingAs($this->operator)->getJson('/api/v2/sesiones/999')->assertNotFound()->assertJsonPath('message', 'La sesión no existe.');
    }

    public function testCatalogoListsValleysWithProcessesAndComponents(): void
    {
        $this->actingAs($this->operator)->getJson('/api/v2/catalogo')
            ->assertOk()
            ->assertExactJson(['valles' => [['id' => $this->valleId, 'nombre' => 'Elqui', 'procesos' => [
                ['id' => $this->procesoId, 'nombre' => 'Pleito', 'componentes' => ['FeT']],
            ]]]]);
    }

    public function testListadoFiltersSortsAndPaginates(): void
    {
        $otroValle = DB::table('valles')->insertGetId(['nombre' => 'Huasco', 'tipo' => 't', 'empresa_id' => DB::table('empresas')->value('id')]);
        $otroProceso = DB::table('procesos')->insertGetId(['nombre' => 'Pellet', 'tipo' => 't', 'valle_id' => $otroValle, 'componentes' => '{"data":["FeT"]}']);
        $ana = User::factory()->create(['nombre' => 'Ana']);
        $mk = function (string $nombre, int $proceso, string $tipo, string $ini, string $fin, User $u) {
            $b = Balances::create(['nombre' => $nombre, 'proceso_id' => $proceso, 'tipo' => $tipo, 'periodo_inicio' => $ini, 'periodo_fin' => $fin, 'user_id' => $u->id]);
            $this->sesion(['balance_id' => $b->id, 'proceso_id' => $proceso, 'user_id' => $u->id]);

            return $b;
        };
        $a = $mk('Pleito octubre', $this->procesoId, 'mensual', '2026-10-01', '2026-10-31', $this->operator);
        $b = $mk('Pleito nov 1Q', $this->procesoId, 'quincenal', '2026-11-01', '2026-11-15', $ana);
        $c = $mk('Pellet nov', $otroProceso, 'mensual', '2026-11-01', '2026-11-30', $ana);

        $ids = fn (string $qs) => $this->actingAs($this->operator)->getJson('/api/v2/balances?'.$qs)->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids("valle_id={$this->valleId}"));
        $this->assertSame([$c->id], $ids("proceso_id={$otroProceso}"));
        $this->assertSame([$b->id], $ids('tipo=quincenal'));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $ids('desde=2026-11-01&hasta=2026-11-30'));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $ids("user_id={$ana->id}"));
        $this->assertSame([$c->id], $ids('q=pellet'));
        $this->assertSame([$c->id, $b->id, $a->id], $ids('orden=nombre&dir=asc'));

        $res = $this->actingAs($this->operator)->getJson('/api/v2/balances?per_page=2&page=2&orden=nombre&dir=asc')->assertOk();
        $this->assertSame([$a->id], $res->json('data.*.id'));
        $res->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);

        $row = collect($this->actingAs($this->operator)->getJson('/api/v2/balances')->json('data'))->firstWhere('id', $c->id);
        $this->assertSame(['id' => $otroValle, 'nombre' => 'Huasco'], $row['valle']);
        $this->assertSame(['id' => $ana->id, 'nombre' => 'Ana'], $row['usuario']);
        $this->assertSame($c->datosEntrada->id, $row['datos_entrada_id']);

        $this->actingAs($this->operator)->getJson('/api/v2/balances?tipo=anual&orden=x')->assertStatus(422);
    }
}
