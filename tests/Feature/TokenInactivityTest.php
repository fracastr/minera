<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
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

    public function testLoginDeletesOnlyExpiredTokens()
    {
        $user = User::factory()->create();
        $user->createToken('expired');
        $this->travel(config('session.lifetime') + 1)->minutes();
        $user->createToken('still-valid');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertEqualsCanonicalizing(['still-valid', 'Personal Access Token'], $user->tokens()->pluck('name')->all());
    }

    public function testScheduledPruneDeletesOnlyIdleTokens()
    {
        $user = User::factory()->create();
        $user->createToken('expired');
        $this->travel(config('session.lifetime') + 1)->minutes();
        $user->createToken('still-valid');

        $event = collect(app(Schedule::class)->events())->firstWhere('description', 'prune-idle-tokens');
        $event->run($this->app);

        $this->assertSame(['still-valid'], $user->tokens()->pluck('name')->all());
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
