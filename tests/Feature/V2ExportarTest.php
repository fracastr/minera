<?php

namespace Tests\Feature;

use App\Models\Balances;
use App\Models\Datos_entrada;
use App\Models\User;
use App\Services\DriveUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class V2ExportarTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private Datos_entrada $sesion;
    /** Arguments of the last excelnode run. */
    private array $args = [];
    /** Flask /get_excel answer; null = a valid one. */
    private $flask = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.flask.url' => 'http://solver.test', 'services.node.path' => '/usr/bin/node']);
        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $valle = DB::table('valles')->insertGetId(['nombre' => 'Elqui', 'tipo' => 't', 'empresa_id' => $empresa]);
        DB::table('procesos')->insert(['id' => 7, 'nombre' => 'Pleito', 'tipo' => 't', 'valle_id' => $valle, 'componentes' => '{"data":["FeT"]}']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        $b = Balances::create(['nombre' => 'Marzo', 'tipo' => 'mensual', 'periodo_inicio' => '2026-03-01', 'periodo_fin' => '2026-03-31', 'proceso_id' => 7, 'user_id' => $this->operator->id]);
        $this->sesion = Datos_entrada::create(['proceso_id' => 7, 'valle_id' => $valle, 'user_id' => $this->operator->id, 'balance_id' => $b->id, 'datos_entrada' => '{}']);
        Http::fake(['solver.test/get_excel' => fn () => $this->flask ?? Http::response(['matriz' => [[1, 2]], 'data_extra' => [['x']]])]);
        // excelnode stand-in: writes <storage>/<id>.xlsx and records the images it was given.
        Process::fake(function (PendingProcess $p) {
            $this->args = $p->command;
            file_put_contents($p->command[7].'/'.$p->command[4].'.xlsx', 'XLSX');

            return Process::result('ok');
        });
    }

    private function drive(?string $link): void
    {
        $this->app->instance(DriveUploader::class, new class($link) extends DriveUploader {
            public function __construct(private ?string $link) {}

            public function upload(string $name, string $contents): string
            {
                return $this->link ?? throw new \RuntimeException('no drive');
            }
        });
    }

    private function exportar(array $files = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->operator, 'sanctum')
            ->post("/api/v2/sesiones/{$this->sesion->id}/exportar", $files, ['Accept' => 'application/json']);
    }

    public function test_streams_the_workbook_with_image_sheets_and_the_drive_link(): void
    {
        $this->drive('https://drive.google.com/file/d/abc/view');
        $res = $this->exportar([
            'diagrama' => UploadedFile::fake()->image('d.png', 40, 20),
            'uso_tolerancia' => UploadedFile::fake()->image('u.png', 10, 10),
        ]);

        $res->assertOk();
        $this->assertSame('XLSX', $res->getContent());
        $this->assertStringContainsString('attachment; filename=elqui_pleito_2026-03-01_2026-03-31.xlsx', $res->headers->get('Content-Disposition'));
        $res->assertHeader('X-Drive-Link', 'https://drive.google.com/file/d/abc/view');
        Http::assertSent(fn ($r) => $r->url() === 'http://solver.test/get_excel' && $r['datos_entrada_id'] === $this->sesion->id && $r['componentes'] === ['FeT']);

        $this->assertStringEndsWith('Exportar_Elqui_Pleito.xlsx', $this->args[6]);
        $hojas = json_decode($this->args[8], true);
        $this->assertSame(['Diagrama Sistema', 'Gráficos Sistema'], array_column($hojas, 'name'));
        $this->assertSame('Uso de tolerancia por medición', $hojas[1]['images'][0]['title']);
        // Temp files (images and the workbook) are gone.
        $this->assertFileDoesNotExist($hojas[0]['images'][0]['path']);
        $this->assertSame([], glob(storage_path('app/tmp/export_'.$this->sesion->id.'_*')));
        $this->assertDatabaseHas('balance_events', ['datos_entrada_id' => $this->sesion->id, 'event_type' => 'exported', 'user_id' => $this->operator->id]);
    }

    public function test_without_images_and_without_drive_it_still_downloads_with_the_v1_arguments(): void
    {
        $this->drive(null);
        $res = $this->exportar();

        $res->assertOk();
        $this->assertFalse($res->headers->has('X-Drive-Link'));
        $this->assertCount(8, $this->args);
    }

    public function test_rejects_files_that_are_not_png(): void
    {
        // A real upload, so the type comes from the content (finfo) and not from the .png name.
        $gif = tempnam(sys_get_temp_dir(), 'gif');
        file_put_contents($gif, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $res = $this->exportar(['diagrama' => new UploadedFile($gif, 'd.png', 'image/png', null, true)])->assertStatus(422);
        $this->assertContains('La imagen diagrama debe ser un PNG.', $res->json('errors.diagrama'));
        $this->exportar(['tendencia' => UploadedFile::fake()->image('t.png', 12001, 1)])->assertStatus(422)->assertJsonValidationErrors('tendencia');
        Process::assertNothingRan();
    }

    public function test_viewers_cannot_export(): void
    {
        $this->exportar([], User::factory()->create(['role' => 'viewer']))->assertForbidden();
    }

    public function test_excelnode_failure_is_a_readable_error_and_cleans_up(): void
    {
        Process::fake(fn () => Process::result('', 'boom', 1));
        $res = $this->exportar(['diagrama' => UploadedFile::fake()->image('d.png', 4, 4)]);

        $res->assertStatus(500)->assertJsonPath('message', 'No se pudo generar el Excel con la plantilla del proceso. Intenta nuevamente; si persiste, avisa al administrador.');
        $this->assertSame([], glob(storage_path('app/tmp/export_'.$this->sesion->id.'_*')));
        $this->assertDatabaseMissing('balance_events', ['event_type' => 'exported']);
    }

    public function test_solver_failure_is_a_502(): void
    {
        $this->flask = Http::response('err', 500);
        $this->exportar()->assertStatus(502)->assertJsonPath('message', 'El solver no pudo preparar los datos del Excel. Corre el balance y vuelve a intentar.');
        Process::assertNothingRan();
    }

    public function test_unknown_session_is_404(): void
    {
        $this->actingAs($this->operator, 'sanctum')->postJson('/api/v2/sesiones/999999/exportar')->assertNotFound();
    }
}
