<?php

namespace Tests\Feature;

use App\Models\BalanceEvent;
use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** /api/analytics on SQLite: the date bucketing used to be MySQL-only DATE_FORMAT. */
class AnalyticsSqliteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $valle = DB::table('valles')->insertGetId(['nombre' => 'Elqui', 'tipo' => 't', 'empresa_id' => $empresa]);
        $proceso = DB::table('procesos')->insertGetId(['nombre' => 'Pleito', 'tipo' => 't', 'valle_id' => $valle, 'componentes' => '{"data":["FeT"]}']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        foreach (['2026-09-03 10:00:00', '2026-09-20 08:00:00', '2026-09-20 18:00:00', '2026-10-05 12:00:00'] as $i => $at) {
            $s = Datos_entrada::create(['proceso_id' => $proceso, 'valle_id' => $valle, 'user_id' => $this->admin->id, 'datos_entrada' => '{}']);
            $s->forceFill(['created_at' => $at])->save();
            $evento = ['datos_entrada_id' => $s->id, 'user_id' => $this->admin->id, 'valle_id' => $valle, 'proceso_id' => $proceso, 'created_at' => $at];
            BalanceEvent::create($evento + ['event_type' => BalanceEvent::TYPE_IMPORTED]);
            if ($i > 0) {
                BalanceEvent::create($evento + ['event_type' => BalanceEvent::TYPE_CORRER]);
            }
        }
    }

    public function testBucketsByMonth(): void
    {
        $this->actingAs($this->admin)->getJson('/api/analytics/dashboard?from=2026-09-01&to=2026-10-31&refresh=1')
            ->assertOk()
            ->assertJsonPath('summary.sessions_imported', 4)
            ->assertJsonPath('summary.balances_corridos', 3)
            ->assertJsonPath('timeseries', [
                ['period' => '2026-09', 'period_label' => 'Septiembre 2026', 'total' => 3],
                ['period' => '2026-10', 'period_label' => 'Octubre 2026', 'total' => 1],
            ])
            ->assertJsonPath('by_valle.0.nombre', 'Elqui');
    }

    public function testDrillsDownToDaysAndCountsRuns(): void
    {
        $this->actingAs($this->admin)->getJson('/api/analytics/timeseries?from=2026-09-01&to=2026-09-30&group_by=day&metric=correr')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', '2026-09-20')
            ->assertJsonPath('data.0.total', 2);
    }

    public function testBucketsByIsoWeek(): void
    {
        $this->actingAs($this->admin)->getJson('/api/analytics/timeseries?from=2026-09-01&to=2026-10-31&group_by=week')
            ->assertOk()
            ->assertJsonPath('data.*.period', ['2026-W36', '2026-W38', '2026-W41']);
    }
}
