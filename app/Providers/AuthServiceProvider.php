<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // The SPA keeps its token in localStorage, so expire it after SESSION_LIFETIME minutes without use.
        Sanctum::authenticateAccessTokensUsing(function ($token, $isValid) {
            $lastActivity = $token->last_used_at ?? $token->created_at;

            return $isValid && $lastActivity->gt(now()->subMinutes(config('session.lifetime')));
        });
    }
}
