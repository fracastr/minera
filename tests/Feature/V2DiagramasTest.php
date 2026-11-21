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

        // Numeric names stay strings (a JSON object keyed by "1" and "0" would come back as a PHP list).
        $layout = [['k' => 'Molienda', 'x' => 10, 'y' => -20.25], ['k' => '1', 'x' => 300.5, 'y' => 0], ['k' => '0', 'x' => 5, 'y' => 6]];
        $this->actingAs($operator)->putJson($this->url(), ['layout' => $layout])->assertOk();
        $this->actingAs($viewer)->getJson($this->url())->assertOk()->assertExactJson(['layout' => [
            ['k' => 'Molienda', 'x' => 10, 'y' => -20.3], // stored with 1 decimal
            ['k' => '1', 'x' => 300.5, 'y' => 0],
            ['k' => '0', 'x' => 5, 'y' => 6],
        ]]);

        // Overwrite keeps one row per process and records who saved it.
        $this->actingAs($operator)->putJson($this->url(), ['layout' => [['k' => 'Molienda', 'x' => 1, 'y' => 2]]])->assertOk();
        $this->assertSame(1, DB::table('proceso_diagramas')->count());
        $this->assertSame($operator->id, DB::table('proceso_diagramas')->value('updated_by'));

        $this->actingAs($operator)->getJson('/api/v2/diagramas')->assertOk()
            ->assertJsonPath('procesos.0.id', $this->procesoId)
            ->assertJsonPath('procesos.0.guardado', true)
            ->assertJsonPath('procesos.0.usuario', $operator->nombre);

        // Rows saved in the earlier {nodo: {x, y}} form are read as entries.
        DB::table('proceso_diagramas')->update(['layout' => json_encode(['Molienda' => ['x' => 3, 'y' => 4]])]);
        $this->actingAs($viewer)->getJson($this->url())->assertExactJson(['layout' => [['k' => 'Molienda', 'x' => 3, 'y' => 4]]]);

        // An empty layout resets to automatic.
        $this->actingAs($operator)->putJson($this->url(), ['layout' => []])->assertOk()->assertExactJson(['layout' => null]);
        $this->assertSame(0, DB::table('proceso_diagramas')->count());
    }

    public function testViewerCannotSaveOrList(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->putJson($this->url(), ['layout' => [['k' => 'A', 'x' => 1, 'y' => 2]]])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v2/diagramas')->assertForbidden();
        $this->assertSame(0, DB::table('proceso_diagramas')->count());
    }

    public function testRejectsMalformedLayouts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tooMany = [];
        for ($i = 0; $i < 501; $i++) {
            $tooMany[] = ['k' => "N{$i}", 'x' => $i, 'y' => 0];
        }
        $bad = [
            null,
            'texto',
            ['A' => ['x' => 1, 'y' => 2]], // the old object form is not accepted on write
            [['x' => 1, 'y' => 2]],
            [['k' => 1, 'x' => 1, 'y' => 2]],
            [['k' => str_repeat('a', 192), 'x' => 1, 'y' => 2]],
            [['k' => 'A', 'x' => '1', 'y' => 2]],
            [['k' => 'A', 'x' => 1, 'y' => 2, 'z' => 3]],
            [['k' => 'A', 'x' => 1e9, 'y' => 2]],
            $tooMany,
        ];
        foreach ($bad as $layout) {
            $this->actingAs($admin)->putJson($this->url(), ['layout' => $layout])->assertStatus(422)->assertJsonValidationErrors('layout');
        }
        $this->actingAs($admin)->putJson('/api/v2/procesos/999999/diagrama', ['layout' => []])->assertNotFound();
        $this->assertSame(0, DB::table('proceso_diagramas')->count());
    }
}
