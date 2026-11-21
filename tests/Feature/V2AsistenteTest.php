<?php

namespace Tests\Feature;

use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class V2AsistenteTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://anthropic.test/v1/messages';

    private User $operator;
    private Datos_entrada $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.flask.url' => 'http://solver.test',
            'services.anthropic' => ['enabled' => true, 'key' => 'test-key', 'model' => 'claude-sonnet-5-5', 'base_url' => 'https://anthropic.test'],
        ]);
        $empresa = DB::table('empresas')->insertGetId(['nombre' => 'E', 'rut' => '1-9']);
        $valle = DB::table('valles')->insertGetId(['nombre' => 'Elqui', 'tipo' => 't', 'empresa_id' => $empresa]);
        $proceso = DB::table('procesos')->insertGetId(['nombre' => 'Pleito', 'tipo' => 't', 'valle_id' => $valle, 'componentes' => '{"data":["Fe T"]}']);
        $this->operator = User::factory()->create(['role' => 'operator', 'nombre' => 'Juana Pérez', 'email' => 'juana@cmp.cl']);
        $this->sesion = Datos_entrada::create([
            'proceso_id' => $proceso, 'valle_id' => $valle, 'user_id' => $this->operator->id,
            'datos_entrada' => json_encode([
                'flujos' => ['F1', 'F2'], 'tags' => ['T1', 'T2'], 'nodos' => ['N1'], 'matriz' => [[1, -1]],
                'mediciones' => [[130, 115, 60, 60], [100, 115, 60, 60]],
                'restricciones' => [[10, 10, 2, 2], [10, 10, 2, 2]], 'jerarquia' => [1, 1],
            ]),
        ]);
        Http::fake(['solver.test/diagnostico' => Http::response([
            'global' => ['gamma' => 25.1, 'gl' => 1, 'critico' => 3.84, 'pasa' => false],
            'global_final' => ['gamma' => 0.0, 'gl' => 0, 'critico' => 0.0, 'pasa' => true],
            'nodos' => [['nodo' => 'N1', 'residuo' => 30.0, 'z' => 5.0, 'sospechoso' => true]],
            'sospechosos' => [[
                'flujo' => 'F1', 'tag' => 'T1', 'variable' => 'tms', 'z' => 5.0, 'valor_medido' => 130.0, 'valor_sugerido' => 100.0,
                'reduccion_gamma' => 25.1, 'accion' => 'corregir_valor',
                'equivalentes' => [['flujo' => 'F2', 'tag' => 'T2', 'variable' => 'tms', 'valor_sugerido' => 130.0]],
            ]],
            'no_redundantes' => [], 'excluidos' => [], 'por_componente' => [],
        ])]);
    }

    private function url(): string
    {
        return "/api/v2/sesiones/{$this->sesion->id}/asistente";
    }

    private function pregunta(string $texto = '¿Qué medición reviso primero?'): array
    {
        return ['mensajes' => [['rol' => 'usuario', 'texto' => $texto]]];
    }

    private static function texto(string $t, int $in = 100, int $out = 20): array
    {
        return ['type' => 'message', 'role' => 'assistant', 'stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $t]], 'usage' => ['input_tokens' => $in, 'output_tokens' => $out]];
    }

    private function anthropicRequests(): array
    {
        return Http::recorded(fn ($r) => $r->url() === self::API)->map(fn ($pair) => $pair[0])->values()->all();
    }

    public function testDisabledReturns404AndUserDataSaysSo(): void
    {
        config(['services.anthropic.enabled' => false]);
        $this->actingAs($this->operator)->postJson($this->url(), $this->pregunta())->assertNotFound();
        $this->actingAs($this->operator)->getJson('/api/auth/user')->assertJsonPath('asistente_habilitado', false);
        Http::assertNothingSent();
    }

    public function testViewerIsForbidden(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->postJson($this->url(), $this->pregunta())->assertForbidden();
        Http::assertNothingSent();
    }

    public function testValidation(): void
    {
        $u = $this->actingAs($this->operator);
        $u->postJson($this->url(), [])->assertStatus(422)->assertJsonValidationErrors('mensajes');
        $u->postJson($this->url(), ['mensajes' => array_fill(0, 21, ['rol' => 'usuario', 'texto' => 'a'])])->assertStatus(422)->assertJsonValidationErrors('mensajes');
        $u->postJson($this->url(), ['mensajes' => [['rol' => 'sistema', 'texto' => 'a']]])->assertStatus(422)->assertJsonValidationErrors('mensajes.0.rol');
        $u->postJson($this->url(), $this->pregunta(str_repeat('a', 4001)))->assertStatus(422)->assertJsonValidationErrors('mensajes.0.texto');
        $u->postJson($this->url(), ['mensajes' => [['rol' => 'usuario', 'texto' => 'a'], ['rol' => 'asistente', 'texto' => 'b']]])->assertStatus(422)->assertJsonValidationErrors('mensajes');
        $u->postJson('/api/v2/sesiones/999999/asistente', $this->pregunta())->assertNotFound();
        Http::assertNothingSent();
    }

    public function testToolUseRoundTripKeepsValidProposalsAndDropsInvalidOnes(): void
    {
        Http::fake([self::API => Http::sequence()
            ->push([
                'type' => 'message', 'role' => 'assistant', 'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'F1 es el principal sospechoso (z = 5).'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'proponer_accion', 'input' => ['flujo' => 'F1', 'variable' => 'tms', 'tipo' => 'corregir_valor', 'valor' => 100, 'motivo' => 'z = 5; el sugerido es 100.']],
                    ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'proponer_accion', 'input' => ['flujo' => 'F9', 'variable' => 'tms', 'tipo' => 'corregir_valor', 'valor' => 1, 'motivo' => 'x']],
                    ['type' => 'tool_use', 'id' => 'tu_3', 'name' => 'proponer_accion', 'input' => ['flujo' => 'F2', 'variable' => 'ley_2', 'tipo' => 'revisar_medicion', 'motivo' => 'x']],
                    ['type' => 'tool_use', 'id' => 'tu_4', 'name' => 'proponer_accion', 'input' => ['flujo' => 'F2', 'variable' => 'tms', 'tipo' => 'corregir_valor', 'valor' => -5, 'motivo' => 'x']],
                ],
                'usage' => ['input_tokens' => 1000, 'output_tokens' => 80, 'cache_creation_input_tokens' => 900],
            ])
            ->push(self::texto('Verifica el pesómetro de F1 en terreno.', 50, 30))]);
        Log::spy();

        $res = $this->actingAs($this->operator)->postJson($this->url(), $this->pregunta())->assertOk();

        $res->assertJsonPath('texto', "F1 es el principal sospechoso (z = 5).\n\nVerifica el pesómetro de F1 en terreno.")
            ->assertJsonCount(1, 'propuestas')
            ->assertJsonPath('propuestas.0', ['flujo' => 'F1', 'variable' => 'tms', 'tipo' => 'corregir_valor', 'valor' => 100, 'motivo' => 'z = 5; el sugerido es 100.'])
            ->assertJsonPath('uso', ['input_tokens' => 1950, 'output_tokens' => 110]);

        $sent = $this->anthropicRequests();
        $this->assertCount(2, $sent);
        $second = $sent[1]->data()['messages'];
        $this->assertCount(3, $second);
        $this->assertSame('assistant', $second[1]['role']);
        $this->assertSame('tu_1', $second[1]['content'][1]['id'], 'the assistant turn goes back unchanged');
        $results = collect($second[2]['content'])->keyBy('tool_use_id');
        $this->assertSame('propuesta registrada', $results['tu_1']['content']);
        $this->assertTrue($results['tu_2']['is_error']);
        $this->assertTrue($results['tu_3']['is_error'], 'ley_2 on a one-component session');
        $this->assertTrue($results['tu_4']['is_error']);

        Log::shouldHaveReceived('info')->with('Asistente: propuesta descartada', \Mockery::on(fn ($c) => $c['razon'] === 'flujo inexistente en la sesión'))->once();
        Log::shouldHaveReceived('info')->with('Asistente: uso', \Mockery::on(fn ($c) => $c['user_id'] === $this->operator->id && $c['sesion'] === $this->sesion->id
            && $c['input_tokens'] === 1050 && $c['output_tokens'] === 110 && !array_key_exists('texto', $c)))->once();
    }

    public function testStopsAfterThreeToolRounds(): void
    {
        $tool = ['type' => 'message', 'role' => 'assistant', 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            'content' => [['type' => 'tool_use', 'id' => 'tu', 'name' => 'proponer_accion', 'input' => ['flujo' => 'F1', 'variable' => 'tms', 'tipo' => 'revisar_medicion', 'motivo' => 'm']]]];
        Http::fake([self::API => Http::response($tool)]);

        $this->actingAs($this->operator)->postJson($this->url(), $this->pregunta())->assertOk()->assertJsonCount(3, 'propuestas');
        $this->assertCount(3, $this->anthropicRequests());
    }

    public function testServerErrorsBecome502AndRateLimitsPassThrough(): void
    {
        Http::fake([self::API => Http::sequence()
            ->push(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']], 500)
            ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'x']], 529)
            ->push(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'x']], 429, ['retry-after' => '17'])
            ->pushFailedConnection()]);
        $u = $this->actingAs($this->operator);

        $u->postJson($this->url(), $this->pregunta())->assertStatus(502)->assertJsonPath('message', 'El asistente no respondió. Intenta nuevamente en unos segundos.');
        $u->postJson($this->url(), $this->pregunta())->assertStatus(502);
        $u->postJson($this->url(), $this->pregunta())->assertStatus(429)->assertHeader('Retry-After', '17');
        $u->postJson($this->url(), $this->pregunta())->assertStatus(502);
    }

    public function testThrottlesTwentyPerMinutePerUser(): void
    {
        Http::fake([self::API => Http::response(self::texto('ok'))]);
        $u = $this->actingAs($this->operator);
        for ($i = 0; $i < 20; $i++) {
            $u->postJson($this->url(), $this->pregunta())->assertOk();
        }
        $u->postJson($this->url(), $this->pregunta())->assertStatus(429)
            ->assertJsonPath('message', 'Hiciste demasiadas consultas al asistente. Intenta nuevamente más tarde.');
        $this->assertCount(20, $this->anthropicRequests());
    }

    public function testPayloadCarriesPromptCacheAndToolButNoPersonalData(): void
    {
        Http::fake([self::API => Http::response(self::texto('ok'))]);
        $this->actingAs($this->operator)->postJson($this->url(), $this->pregunta())->assertOk();

        $r = $this->anthropicRequests()[0];
        $this->assertSame('test-key', $r->header('x-api-key')[0]);
        $this->assertNotEmpty($r->header('anthropic-version')[0]);
        $p = $r->data();
        $this->assertSame('claude-sonnet-5-5', $p['model']);
        $this->assertStringContainsString('Nunca inventes números', $p['system'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $p['system'][1]['cache_control']);
        $this->assertStringContainsString('"valor_sugerido":100.0', $p['system'][1]['text']);
        $this->assertStringContainsString('"equivalentes":[{"flujo":"F2"', $p['system'][1]['text']);
        $this->assertSame('proponer_accion', $p['tools'][0]['name']);
        $this->assertSame(['tms', 'ley_1', 'ley_2'], $p['tools'][0]['input_schema']['properties']['variable']['enum']);
        $this->assertArrayNotHasKey('tool_choice', $p, 'forced tool use is rejected by the model');
        $this->assertSame([['role' => 'user', 'content' => '¿Qué medición reviso primero?']], $p['messages']);

        $raw = json_encode($p, JSON_UNESCAPED_UNICODE);
        foreach (['Juana', 'Pérez', 'juana@cmp.cl', 'user_id'] as $pii) {
            $this->assertStringNotContainsString($pii, $raw);
        }
    }

    public function testReusesTheCachedDiagnosisAcrossTurns(): void
    {
        Http::fake([self::API => Http::response(self::texto('ok'))]);
        $u = $this->actingAs($this->operator);
        $u->getJson("/api/v2/sesiones/{$this->sesion->id}/diagnostico")->assertOk();
        $u->postJson($this->url(), $this->pregunta())->assertOk();
        $u->postJson($this->url(), ['mensajes' => [['rol' => 'usuario', 'texto' => 'a'], ['rol' => 'asistente', 'texto' => 'b'], ['rol' => 'usuario', 'texto' => 'c']]])->assertOk();

        Http::assertSentCount(3); // one Flask call, two model calls
        $sent = $this->anthropicRequests();
        $this->assertSame($sent[0]->data()['system'], $sent[1]->data()['system'], 'stable prefix for the prompt cache');
    }
}
