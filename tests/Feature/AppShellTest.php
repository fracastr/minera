<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppShellTest extends TestCase
{
    public function test_every_page_serves_the_v2_app(): void
    {
        $this->withoutVite();

        foreach (['/', '/login', '/balances', '/balances/sesion/1', '/forgot-password', '/reset-password/abc?email=a%40b.cl'] as $path)
            $this->get($path)->assertOk()->assertViewIs('app');
    }

    public function test_old_v3_links_redirect_to_the_root(): void
    {
        $this->get('/v3')->assertStatus(301)->assertRedirect('/');
        $this->get('/v3/login?sesion=expirada')->assertStatus(301)->assertRedirect('/login?sesion=expirada');
        $this->get('/v3/balances/Listado')->assertStatus(301)->assertRedirect('/balances/Listado');
    }

    public function test_old_vue2_paths_redirect_to_their_v2_equivalents(): void
    {
        $this->get('/balances/Listado')->assertStatus(301)->assertRedirect('/balances');
        $this->get('/dashboard/balances')->assertStatus(301)->assertRedirect('/inicio');
        $this->get('/dashboard/balance-analytics')->assertStatus(301)->assertRedirect('/analytics');
        $this->get('/admin/users')->assertStatus(301)->assertRedirect('/admin/usuarios');
        $this->get('/balances/BalanceFormWizard')->assertRedirect('/balances/nuevo');
        $this->get('/balances/BalanceFormWizard?balance_id=abc')->assertRedirect('/balances');
    }
}
