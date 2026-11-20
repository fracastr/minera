<?php

namespace Tests\Feature;

use Tests\TestCase;

class V3ShellTest extends TestCase
{
    public function test_every_page_serves_the_vue3_shell(): void
    {
        $this->withoutVite();

        foreach (['/', '/login', '/balances/Listado', '/forgot-password', '/reset-password/abc'] as $path)
            $this->get($path)->assertOk()->assertViewIs('application-v3');
    }

    public function test_old_v3_links_redirect_to_the_root(): void
    {
        $this->get('/v3')->assertStatus(301)->assertRedirect('/');
        $this->get('/v3/login?sesion=expirada')->assertStatus(301)->assertRedirect('/login?sesion=expirada');
        $this->get('/v3/balances/Listado')->assertStatus(301)->assertRedirect('/balances/Listado');
    }
}
