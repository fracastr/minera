<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function testForgotPasswordIsThrottledPerEmail()
    {
        Bus::fake(); // the mail is sent after the response; only the throttle matters here

        foreach (range(1, 5) as $i)
            $this->postJson('/api/auth/forgot-password', ['email' => 'Ana@CMP.cl'])->assertOk();

        $this->postJson('/api/auth/forgot-password', ['email' => 'ana@cmp.cl'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Demasiados intentos. Intenta nuevamente mas tarde.');
        // Another account from the same IP is still allowed.
        $this->postJson('/api/auth/forgot-password', ['email' => 'otro@cmp.cl'])->assertOk();
    }

    public function testForgotPasswordIsThrottledPerIp()
    {
        Bus::fake();

        foreach (range(1, 20) as $i)
            $this->postJson('/api/auth/forgot-password', ['email' => "u{$i}@cmp.cl"])->assertOk();

        $this->postJson('/api/auth/forgot-password', ['email' => 'u21@cmp.cl'])->assertStatus(429);
    }

    public function testResetPasswordIsThrottled()
    {
        Http::fake(); // StrongPassword's breach lookup
        $body = ['token' => 'x', 'email' => 'ana@cmp.cl', 'password' => 'Valle-Huasco-2026!x', 'password_confirmation' => 'Valle-Huasco-2026!x'];

        foreach (range(1, 5) as $i)
            $this->postJson('/api/auth/reset-password', $body)->assertStatus(400);

        $this->postJson('/api/auth/reset-password', $body)->assertStatus(429);
    }

    public function testResetLinkUsesAppUrlNotTheRequestHost()
    {
        Notification::fake();
        config(['app.url' => 'https://v2.sondek.cl']);
        $user = User::factory()->create();

        $this->withHeader('Host', 'evil.example')
            ->withHeader('X-Forwarded-Host', 'evil.example')
            ->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://v2.sondek.cl/reset-password/');
        });
    }

    public function testUserEndpointReturnsUserDataWithAbilities()
    {
        $user = User::factory()->create(['role' => 'viewer', 'dashboard_access' => true]);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('role', 'viewer')
            ->assertJsonPath('fullName', $user->nombre)
            ->assertJsonFragment(['action' => 'read', 'subject' => 'DashboardAnalytics']);
    }

    public function testTokensHaveAnAbsoluteLifetimeEvenWhenActive()
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        // Used every 100 min (under the idle limit) until past the 12 h absolute limit.
        foreach (range(1, 7) as $i) {
            $this->travel(100)->minutes();
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/auth/user')->assertOk();
        }
        $this->travel(100)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function testOldV3LinksCannotRedirectOffSite()
    {
        $this->get('/v3//evil.com')->assertStatus(301)->assertRedirect('/evil.com');
    }
}
