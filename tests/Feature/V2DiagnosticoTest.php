<?php

namespace Tests\Feature;

use App\Models\BalanceCorrida;
use App\Models\Balances;
use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class V2DiagnosticoTest extends TestCase
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

    /** Two flows; F1 measured 100 and reconciled to $rec (tolerance 10 %), its grade 60 → $ley. */
    private function datos(float $rec = 100, float $ley = 60): array
    {
        return [
            'flujos' => ['F1', 'F2'], 'tags' => ['T1', 'T2'], 'nodos' => ['N1'], 'matriz' => [[1, -1]],
            'mediciones' => [[100, $rec, 60, $ley], [100, $rec, 60, 60]],
            'restricciones' => [[10, 10, 2, 2], [10, 10, 2, 2]], 'jerarquia' => [1, 1],
        ];
    }

    private function sesion(?array $datos = null, array $attrs = []): Datos_entrada
    {
        return Datos_entrada::create($attrs + [
            'proceso_id' => $this->procesoId, 'valle_id' => $this->valleId, 'user_id' => $this->operator->id,
            'datos_entrada' => json_encode($datos ?? $this->datos()),
        ]);
    }

    /** A saved balance of the process whose last run left F1 at $rec. */
    private function balanceGuardado(string $fin, float $rec, float $ley = 60, bool $conCorrida = true): Balances
    {
        $b = Balances::create(['nombre' => "B {$fin}", 'tipo' => 'mensual', 'periodo_inicio' => $fin, 'periodo_fin' => $fin, 'proceso_id' => $this->procesoId, 'user_id' => $this->operator->id]);
        $s = $this->sesion($conCorrida ? $this->datos() : $this->datos($rec, $ley), ['balance_id' => $b->id]);
        if ($conCorrida) {
            BalanceCorrida::create(['datos_entrada_id' => $s->id, 'tipo' => 'corrida', 'snapshot' => json_encode($this->datos(999)), 'created_at' => now()]);
            BalanceCorrida::create(['datos_entrada_id' => $s->id, 'tipo' => 'corrida', 'snapshot' => json_encode($this->datos($rec, $ley)), 'created_at' => now()]);
            BalanceCorrida::create(['datos_entrada_id' => $s->id, 'tipo' => 'restauracion', 'snapshot' => json_encode($this->datos(1)), 'created_at' => now()]);
        }

        return $b;
    }

    private function flask(): array
    {
        return [
            'global' => ['gamma' => 25.1, 'gl' => 1, 'critico' => 3.84, 'pasa' => false],
            'global_final' => ['gamma' => 0.0, 'gl' => 0, 'critico' => 0.0, 'pasa' => true],
            'nodos' => [['nodo' => 'N1', 'residuo' => 30.0, 'z' => 5.0, 'sospechoso' => true]],
            'sospechosos' => [[
                'flujo' => 'F1', 'tag' => 'T1', 'variable' => 'tms', 'z' => 5.0, 'valor_medido' => 130.0, 'valor_sugerido' => 100.0,
                'reduccion_gamma' => 25.1, 'accion' => 'corregir_valor',
                'equivalentes' => [['flujo' => 'F2', 'tag' => 'T2', 'variable' => 'tms', 'valor_sugerido' => 130.0]],
            ]],
            'no_redundantes' => [], 'excluidos' => [], 'por_componente' => [],
        ];
    }

    public function testPassesTheDiagnosisThroughWithTheTrend(): void
    {
        Http::fake(['solver.test/diagnostico' => Http::response($this->flask())]);
        $s = $this->sesion();

        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}/diagnostico")
            ->assertOk()
            ->assertJsonPath('diagnostico.global.pasa', false)
            ->assertJsonPath('diagnostico.sospechosos.0.equivalentes.0.flujo', 'F2')
            ->assertJsonPath('tendencia', []);
        Http::assertSent(fn ($r) => $r->url() === 'http://solver.test/diagnostico' && $r['datos_entrada_id'] === $s->id);
    }

    public function testFlaskErrorsMapToReadableStatuses(): void
    {
        $s = $this->sesion();
        Http::fakeSequence('solver.test/*')
            ->push(['error' => 'datos de entrada inválidos para el diagnóstico'], 422)
            ->push(['error' => 'base de datos no disponible'], 503)
            ->push('Not Found', 404)
            ->push(['error' => 'datos_entrada_id debe ser un entero'], 400)
            ->push(['global' => []])
            ->pushFailedConnection();
        $noDisponible = 'Diagnóstico no disponible (servicio en actualización).';
        $cases = [
            [422, 'No se pudo calcular el diagnóstico: datos de entrada inválidos para el diagnóstico.'],
            [503, $noDisponible],
            [503, $noDisponible], // a Flask without /diagnostico yet
            [502, 'El diagnóstico falló en el servidor de cálculo. Intenta nuevamente; si persiste, avisa al administrador.'],
            [502, 'El servidor de cálculo devolvió un diagnóstico inválido.'],
            [503, $noDisponible],
        ];
        foreach ($cases as [$status, $message]) {
            $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}/diagnostico")
                ->assertStatus($status)->assertJsonPath('message', $message);
        }
    }

    public function testTrendUsesTheLatestRunOfTheLastEightBalancesAndFlagsARecurringBias(): void
    {
        Http::fake(['solver.test/diagnostico' => Http::response($this->flask())]);
        // Oldest is outside the 8; then 3 of the last 4 move F1 up by 8 % (> half its 10 % tolerance).
        $this->balanceGuardado('2026-01-31', 50);
        foreach (['2026-02-28' => 101, '2026-03-31' => 99, '2026-04-30' => 101, '2026-05-31' => 102] as $fin => $rec) {
            $this->balanceGuardado($fin, $rec);
        }
        $this->balanceGuardado('2026-06-30', 108, 59.5);
        $this->balanceGuardado('2026-07-31', 97, 59.5);
        $this->balanceGuardado('2026-08-31', 108, 59.5);
        $ultimo = $this->balanceGuardado('2026-09-30', 108, 59.5, conCorrida: false); // v1 balance: no run history
        $s = $this->sesion();

        $t = $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}/diagnostico")->assertOk()->json('tendencia');

        $f1 = $t['F1|T1'];
        $this->assertSame('T1', $f1['tag']);
        $this->assertCount(8, $f1['serie']);
        $this->assertSame('2026-02-28', $f1['serie'][0]['periodo']);
        $this->assertSame($ultimo->id, $f1['serie'][7]['balance_id']);
        $this->assertEqualsWithDelta(8.0, $f1['serie'][7]['ajuste_pct'], 1e-9);
        $this->assertTrue($f1['sesgo_recurrente']);
        $this->assertSame(1, $f1['signo']);
        // Grade 60 → 59.5 is −0.83 %, within half of its 2 % tolerance: no bias.
        $this->assertEqualsWithDelta(-0.8333, $f1['leyes'][0]['serie'][7]['ajuste_pct'], 1e-3);
        $this->assertFalse($f1['leyes'][0]['sesgo_recurrente']);
        $this->assertNull($f1['leyes'][0]['signo']);
    }

    public function testDiagnosisIsCachedUntilTheSessionChanges(): void
    {
        Http::fake(['solver.test/diagnostico' => Http::response($this->flask())]);
        $s = $this->sesion();
        $url = "/api/v2/sesiones/{$s->id}/diagnostico";

        $this->actingAs($this->operator)->getJson($url)->assertOk();
        $this->actingAs($this->operator)->getJson($url)->assertOk();
        Http::assertSentCount(1);

        $this->travel(5)->seconds();
        $s->touch();
        $this->actingAs($this->operator)->getJson($url)->assertOk();
        Http::assertSentCount(2);
    }

    public function testErrorsAreNotCached(): void
    {
        $s = $this->sesion();
        Http::fakeSequence('solver.test/*')->push(['error' => 'x'], 503)->push($this->flask());

        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}/diagnostico")->assertStatus(503);
        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}/diagnostico")->assertOk();
    }

    public function testViewerCanReadTheDiagnosisAndMissingSessionIs404(): void
    {
        Http::fake(['solver.test/diagnostico' => Http::response($this->flask())]);
        $viewer = User::factory()->create(['role' => 'viewer']);
        $s = $this->sesion();

        $this->actingAs($viewer)->getJson("/api/v2/sesiones/{$s->id}/diagnostico")->assertOk();
        $this->actingAs($viewer)->getJson('/api/v2/sesiones/999999/diagnostico')->assertNotFound();
    }

    public function testSolverStatusIsPersistedAndExposedAndOldFlaskMeansNull(): void
    {
        $s = $this->sesion();
        $respuesta = fn (?array $solver) => array_filter([
            'datos_entrada' => json_encode($this->datos()),
            'balances_finales' => json_encode([[100, 100, 60, 60], [100, 100, 60, 60]]),
            'desviaciones' => json_encode([[0, 0, 0, 0], [0, 0, 0, 0]]),
            'nodos_data' => json_encode([[0, 0]]),
            'solver' => $solver === null ? null : json_encode($solver),
        ]);

        Http::fakeSequence('solver.test/correr_balance')
            ->push($respuesta(['ok' => false, 'appstatus' => 0, 'solvestatus' => 0, 'iteraciones' => 250, 'mensaje' => 'Maximum iterations']))
            ->push($respuesta(null));
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$s->id}/correr")
            ->assertOk()->assertJsonPath('solver', ['ok' => false, 'mensaje' => 'Maximum iterations', 'iteraciones' => 250]);
        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}")
            ->assertJsonPath('solver.ok', false)->assertJsonPath('corrida', true);
        $this->assertSame(['ok' => false, 'mensaje' => 'Maximum iterations', 'iteraciones' => 250, 'hash' => md5($s->fresh()->datos_entrada)], $s->fresh()->solver_estado);
        $this->assertArrayNotHasKey('solver', json_decode($s->fresh()->datos_entrada, true)); // v1's JSON is untouched

        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$s->id}/correr")->assertOk()->assertJsonPath('solver', null);
    }

    public function testSolverStatusGoesStaleWhenTheDataChangesAfterTheRun(): void
    {
        $s = $this->sesion();
        Http::fake(['solver.test/correr_balance' => Http::response([
            'datos_entrada' => json_encode($this->datos()),
            'balances_finales' => json_encode([[100, 100, 60, 60], [100, 100, 60, 60]]),
            'desviaciones' => json_encode([[0, 0, 0, 0], [0, 0, 0, 0]]),
            'nodos_data' => json_encode([[0, 0]]),
            'solver' => json_encode(['ok' => false, 'iteraciones' => 9, 'mensaje' => 'Solution Not Found']),
        ])]);
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$s->id}/correr")->assertJsonPath('solver.ok', false);

        // An edit saved after the failed run (PUT datos): the status no longer describes these data.
        $edit = ['flujos' => [['idx' => 0, 'tms_medido' => 95, 'leyes_medido' => [60], 'tms_tol_inf' => 10, 'tms_tol_sup' => 10, 'leyes_tol' => [[2, 2]], 'jerarquia' => 1]]];
        $this->actingAs($this->operator)->putJson("/api/v2/sesiones/{$s->id}/datos", $edit)->assertOk()->assertJsonPath('solver', null);

        // Same for a v1 re-run writing the shared row directly.
        $this->actingAs($this->operator)->postJson("/api/v2/sesiones/{$s->id}/correr")->assertJsonPath('solver.ok', false);
        DB::table('datos_entradas')->where('id', $s->id)->update(['datos_entrada' => json_encode($this->datos(101))]);
        $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$s->id}")->assertJsonPath('solver', null);
    }

    public function testTrendIgnoresNoiseOnZeroToleranceAndSkipsDuplicateNames(): void
    {
        Http::fake(['solver.test/diagnostico' => Http::response($this->flask())]);
        // tol 0 → 5 % default: a 0.01 % adjustment four times in a row is noise, not bias.
        $datos = fn (float $rec) => [
            'flujos' => ['F1', 'Dup', 'Dup'], 'tags' => ['T1', 'TD', 'TD'], 'nodos' => ['N1'], 'matriz' => [[1, -1, 0]],
            'mediciones' => [[100, $rec, 60, 60], [100, 120, 60, 60], [100, 80, 60, 60]],
            'restricciones' => [[0, 0, 0, 0], [10, 10, 2, 2], [10, 10, 2, 2]], 'jerarquia' => [1, 1, 1],
        ];
        foreach (['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'] as $fin) {
            $b = Balances::create(['nombre' => $fin, 'tipo' => 'mensual', 'periodo_inicio' => $fin, 'periodo_fin' => $fin, 'proceso_id' => $this->procesoId, 'user_id' => $this->operator->id]);
            $this->sesion($datos(100.01), ['balance_id' => $b->id]);
        }

        $t = $this->actingAs($this->operator)->getJson("/api/v2/sesiones/{$this->sesion()->id}/diagnostico")->assertOk()->json('tendencia');

        $this->assertSame(['F1|T1'], array_keys($t));
        $this->assertSame(['F1', 'T1'], [$t['F1|T1']['flujo'], $t['F1|T1']['tag']]);
        $this->assertCount(4, $t['F1|T1']['serie']);
        $this->assertFalse($t['F1|T1']['sesgo_recurrente']);
    }
}
