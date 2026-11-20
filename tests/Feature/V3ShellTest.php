<?php

namespace Tests\Feature;

use Tests\TestCase;

class V3ShellTest extends TestCase
{
    public function test_v3_routes_serve_the_vue3_shell(): void
    {
        $this->withoutVite();

        $this->get('/v3')->assertOk()->assertViewIs('application-v3');
        $this->get('/v3/login')->assertOk()->assertViewIs('application-v3');
    }

    public function test_current_app_keeps_its_catch_all(): void
    {
        $this->withoutVite();

        $this->get('/balances/Listado')->assertOk()->assertViewIs('application');
        $this->get('/v3x')->assertOk()->assertViewIs('application');
    }
}
