<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function testPostLogoutRevokesOnlyTheCurrentToken()
    {
        $user = User::factory()->create();
        $thisDevice = $user->createToken('this-device')->plainTextToken;
        $otherDevice = $user->createToken('other-device')->plainTextToken;

        $this->withToken($thisDevice)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->assertSame(['other-device'], $user->tokens()->pluck('name')->all());
        $this->withToken($thisDevice)->getJson('/api/auth/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherDevice)->getJson('/api/auth/user')->assertOk();
    }

    public function testPostLogoutRequiresAuthentication()
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function testLegacyGetLogoutStillRevokesEveryToken()
    {
        $user = User::factory()->create();
        $token = $user->createToken('a')->plainTextToken;
        $user->createToken('b');

        $this->withToken($token)->getJson('/api/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
