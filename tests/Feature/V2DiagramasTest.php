<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2DiagramasTest extends TestCase
{
    use RefreshDatabase;

    private int $procesoId;

    protected function setUp(): void
    {
        parent::setUp();
        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $valle = DB::table('valles')->insertGetId(['nombre' => 'Elqui', 'tipo' => 't', 'empresa_id' => $empresa]);
        $this->procesoId = DB::table('procesos')->insertGetId([
            'nombre' => 'Pleito', 'tipo' => 't', 'valle_id' => $valle, 'componentes' => '{"data":["FeT","FeMag"]}',
        ]);
    }

    private function url(): string
    {
        return "/api/v2/procesos/{$this->procesoId}/diagrama";
    }

    public function testRoundTripAndAdminListing(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($viewer)->getJson($this->url())->assertOk()->assertExactJson(['layout' => null]);

        $layout = ['Molienda' => ['x' => 10, 'y' => -20.25], 'Nodo 2' => ['x' => 300.5, 'y' => 0]];
        $this->actingAs($operator)->putJson($this->url(), ['layout' => $layout])->assertOk();
        $this->actingAs($viewer)->getJson($this->url())->assertOk()
            ->assertJsonPath('layout.Molienda.x', 10)
            ->assertJsonPath('layout.Molienda.y', -20.3) // stored with 1 decimal
            ->assertJsonPath('layout.Nodo 2.x', 300.5);

        // Overwrite keeps one row per process and records who saved it.
        $this->actingAs($operator)->putJson($this->url(), ['layout' => ['Molienda' => ['x' => 1, 'y' => 2]]])->assertOk();
        $this->assertSame(1, DB::table('proceso_diagramas')->count());
        $this->assertSame($operator->id, DB::table('proceso_diagramas')->value('updated_by'));

        $this->actingAs($operator)->getJson('/api/v2/diagramas')->assertOk()
            ->assertJsonPath('procesos.0.id', $this->procesoId)
            ->assertJsonPath('procesos.0.guardado', true)
            ->assertJsonPath('procesos.0.usuario', $operator->nombre);

        // An empty layout resets to automatic.
        $this->actingAs($operator)->putJson($this->url(), ['layout' => []])->assertOk()->assertExactJson(['layout' => null]);
        $this->assertSame(0, DB::table('proceso_diagramas')->count());
    }

    public function testViewerCannotSaveOrList(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->putJson($this->url(), ['layout' => ['A' => ['x' => 1, 'y' => 2]]])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v2/diagramas')->assertForbidden();
        $this->assertSame(0, DB::table('proceso_diagramas')->count());
    }

    public function testRejectsMalformedLayouts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tooMany = [];
        for ($i = 0; $i < 501; $i++) {
            $tooMany["N{$i}"] = ['x' => $i, 'y' => 0];
        }
        $bad = [
            null,
            'texto',
            [['x' => 1, 'y' => 2]], // a list, not {nodo: pos}
            ['A' => ['x' => 1]],
            ['A' => ['x' => '1', 'y' => 2]],
            ['A' => ['x' => 1, 'y' => 2, 'z' => 3]],
            ['A' => ['x' => 1e9, 'y' => 2]],
            $tooMany,
        ];
        foreach ($bad as $layout) {
            $this->actingAs($admin)->putJson($this->url(), ['layout' => $layout])->assertStatus(422)->assertJsonValidationErrors('layout');
        }
        $this->actingAs($admin)->putJson('/api/v2/procesos/999999/diagrama', ['layout' => []])->assertNotFound();
        $this->assertSame(0, DB::table('proceso_diagramas')->count());
    }
}
