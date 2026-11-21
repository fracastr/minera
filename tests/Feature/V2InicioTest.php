<?php

namespace Tests\Feature;

use App\Models\BalanceCorrida;
use App\Models\Balances;
use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2InicioTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private int $valleId;
    private int $procesoId;

    protected function setUp(): void
    {
        parent::setUp();
        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $this->valleId = DB::table('valles')->insertGetId(['nombre' => 'Huasco', 'tipo' => 't', 'empresa_id' => $empresa]);
        $this->procesoId = DB::table('procesos')->insertGetId([
            'nombre' => 'Planta Pellets', 'tipo' => 't', 'valle_id' => $this->valleId, 'componentes' => '{"data":["FeT"]}',
        ]);
        $this->operator = User::factory()->create(['role' => 'operator']);
    }

    /** F1 100 → $f1 and F2 100 → $f2 (TMS tolerance 10 %), grades untouched; node N1 with residual $residuo. */
    private function datos(float $f1 = 100, float $f2 = 100, float $residuo = 0): array
    {
        return [
            'flujos' => ['F1', 'F2'], 'tags' => ['T1', 'T2'], 'nodos' => ['N1'], 'matriz' => [[1, -1]],
            'mediciones' => [[100, $f1, 60, 60], [100, $f2, 60, 60]],
            'restricciones' => [[10, 10, 2, 2], [10, 10, 2, 2]], 'jerarquia' => [1, 1],
            'nodos_data' => [[$residuo, 0]],
        ];
    }

    private function sesion(array $datos, array $attrs = []): Datos_entrada
    {
        return Datos_entrada::create($attrs + [
            'proceso_id' => $this->procesoId, 'valle_id' => $this->valleId, 'user_id' => $this->operator->id,
            'datos_entrada' => json_encode($datos),
        ]);
    }

    private function guardado(string $nombre, string $fin, array $datos): Datos_entrada
    {
        $b = Balances::create(['nombre' => $nombre, 'tipo' => 'mensual', 'periodo_inicio' => $fin, 'periodo_fin' => $fin, 'proceso_id' => $this->procesoId, 'user_id' => $this->operator->id]);

        return $this->sesion($datos, ['balance_id' => $b->id]);
    }

    public function testAlertsUseTheLatestSavedBalanceOfEachProcessWithTheClientRules(): void
    {
        $this->guardado('Octubre', '2026-10-31', $this->datos());
        // F1 +12 % on a 10 % tolerance (out), F2 +8 % (80 % of it: at the limit), N1 misses by 30 of 110 t.
        $ultima = $this->guardado('Noviembre', '2026-11-30', $this->datos(112, 108, 30));
        $ultima->solver_estado = ['ok' => false, 'mensaje' => 'no convergió', 'hash' => md5($ultima->datos_entrada)];
        $ultima->save();

        $this->actingAs($this->operator)->getJson('/api/v2/inicio')
            ->assertOk()
            ->assertJsonCount(1, 'alertas')
            ->assertJsonPath('alertas.0.nombre', 'Noviembre')
            ->assertJsonPath('alertas.0.sesion_id', $ultima->id)
            ->assertJsonPath('alertas.0.proceso', 'Planta Pellets')
            ->assertJsonPath('alertas.0.valle', 'Huasco')
            ->assertJsonPath('alertas.0.fuera', 1)
            ->assertJsonPath('alertas.0.limite', 1)
            ->assertJsonPath('alertas.0.nodos_sin_cerrar', 1)
            ->assertJsonPath('alertas.0.solver_ok', false);
    }

    public function testRecentSessionsAreTheUsersOwnNewestSix(): void
    {
        $otro = User::factory()->create(['role' => 'operator']);
        $this->sesion($this->datos(), ['user_id' => $otro->id]);
        foreach (range(1, 6) as $i) {
            $this->sesion($this->datos())->forceFill(['updated_at' => now()->subDays(10 - $i)])->save();
        }
        $corrida = $this->sesion($this->datos());
        BalanceCorrida::record($corrida, $this->operator, BalanceCorrida::TIPO_CORRIDA);
        $guardada = $this->guardado('Guardado', '2026-11-30', $this->datos());

        $r = $this->actingAs($this->operator)->getJson('/api/v2/inicio')->assertOk()->json('recientes');

        $this->assertCount(6, $r);
        $porId = collect($r)->keyBy('id');
        $this->assertSame('guardado', $porId[$guardada->id]['estado']);
        $this->assertSame('Guardado', $porId[$guardada->id]['nombre']);
        $this->assertSame('corrido', $porId[$corrida->id]['estado']);
        $this->assertNull($porId[$corrida->id]['nombre']);
        $this->assertSame('sin-correr', $r[5]['estado']);
        $this->assertNull($r[0]['solver_ok']);
    }

    public function testAViewerReadsItAndGuestsCannot(): void
    {
        $this->guardado('Noviembre', '2026-11-30', $this->datos(112));
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->getJson('/api/v2/inicio')->assertUnauthorized();
        $this->actingAs($viewer)->getJson('/api/v2/inicio')
            ->assertOk()
            ->assertJsonPath('recientes', [])
            ->assertJsonPath('alertas.0.fuera', 1);
    }

    public function testTheGlobalPartIsCachedUntilASavedSessionChanges(): void
    {
        $s = $this->guardado('Noviembre', '2026-11-30', $this->datos(112));
        $this->actingAs($this->operator)->getJson('/api/v2/inicio')->assertJsonPath('alertas.0.fuera', 1);

        // Same stamp (updated_at untouched): served from the cache.
        DB::table('datos_entradas')->where('id', $s->id)->update(['datos_entrada' => json_encode($this->datos())]);
        $this->getJson('/api/v2/inicio')->assertJsonPath('alertas.0.fuera', 1);

        DB::table('datos_entradas')->where('id', $s->id)->update(['updated_at' => now()->addMinute()]);
        $this->getJson('/api/v2/inicio')->assertJsonPath('alertas.0.fuera', 0);
    }

    public function testListsMetersWithARecurringBias(): void
    {
        foreach (['2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30'] as $fin) {
            $this->guardado("B {$fin}", $fin, $this->datos(108)); // F1 +8 %: beyond half its 10 % tolerance, every time
        }

        $this->actingAs($this->operator)->getJson('/api/v2/inicio')
            ->assertOk()
            ->assertJsonCount(1, 'sesgos')
            ->assertJsonPath('sesgos.0.flujo', 'F1')
            ->assertJsonPath('sesgos.0.tag', 'T1')
            ->assertJsonPath('sesgos.0.variable', 'TMS')
            ->assertJsonPath('sesgos.0.signo', 1)
            ->assertJsonPath('sesgos.0.proceso', 'Planta Pellets')
            ->assertJsonPath('sesgos.0.balances', 4);
    }
}
