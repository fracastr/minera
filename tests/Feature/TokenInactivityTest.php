<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenInactivityTest extends TestCase
{
    use RefreshDatabase;

    public function testTokenExpiresAfterInactivity()
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/user')->assertOk();

        $this->travel(config('session.lifetime') + 1)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function testActivityKeepsTokenAlive()
    {
        $token = User::factory()->create()->createToken('test')->plainTextToken;
        $almostExpired = config('session.lifetime') - 1;

        foreach (range(1, 3) as $i) {
            $this->travel($almostExpired)->minutes();
            $this->app['auth']->forgetGuards();

            $this->withToken($token)->getJson('/api/auth/user')->assertOk();
        }
    }
}
