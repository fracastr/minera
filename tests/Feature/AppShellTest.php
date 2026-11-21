<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppShellTest extends TestCase
{
    public function test_every_page_serves_the_v2_app(): void
    {
        $this->withoutVite();

        foreach (['/', '/login', '/balances/Listado', '/forgot-password', '/reset-password/abc?email=a%40b.cl'] as $path)
            $this->get($path)->assertOk()->assertViewIs('app');
    }

    public function test_old_v3_links_redirect_to_the_root(): void
    {
        $this->get('/v3')->assertStatus(301)->assertRedirect('/');
        $this->get('/v3/login?sesion=expirada')->assertStatus(301)->assertRedirect('/login?sesion=expirada');
        $this->get('/v3/balances/Listado')->assertStatus(301)->assertRedirect('/balances/Listado');
    }
}
