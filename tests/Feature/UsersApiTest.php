<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    }

    public function testValidationMessagesAreReadableSpanish(): void
    {
        $this->actingAs($this->admin)->postJson('/api/users', [
            'email' => 'admin@example.com', 'role' => 'jefe', 'password' => 'Segura-Clave-2026', 'password_confirmation' => 'otra',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.')
            ->assertJsonPath('errors.email.0', 'Ya existe un usuario con ese correo.')
            ->assertJsonPath('errors.role.0', 'El rol debe ser administrador, operador o lector.')
            ->assertJsonPath('errors.password.0', 'Las contraseñas no coinciden.');
    }

    public function testResponsesNeverCarryPasswordHashes(): void
    {
        $body = ['name' => 'Ana Rojas', 'email' => 'ana@example.com', 'role' => 'viewer', 'password' => 'Segura-Clave-2026', 'password_confirmation' => 'Segura-Clave-2026'];
        $created = $this->actingAs($this->admin)->postJson('/api/users', $body)->assertCreated();
        $id = $created->json('user.id');
        $updated = $this->putJson("/api/users/{$id}", ['role' => 'operator', 'dashboard_access' => true])->assertOk()
            ->assertJsonPath('user.role', 'operator')->assertJsonPath('user.dashboard_access', true);
        $index = $this->getJson('/api/users')->assertOk();

        foreach ([$created, $updated, $index] as $r) {
            $this->assertStringNotContainsString('password', $r->getContent());
            $this->assertStringNotContainsString('remember_token', $r->getContent());
        }
    }

    public function testAdminCannotDeleteThemselvesAndOthersCannotManageUsers(): void
    {
        $this->actingAs($this->admin)->deleteJson("/api/users/{$this->admin->id}")
            ->assertStatus(422)->assertJsonPath('message', 'No puedes eliminar tu propia cuenta.');

        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($operator)->getJson('/api/users')->assertForbidden();
    }
}
