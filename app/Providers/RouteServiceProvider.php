<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $maxAttempts = max((int) config('auth.login_rate_limit.max_attempts', 5), 1);
            $ipMaxAttempts = max((int) config('auth.login_rate_limit.ip_max_attempts', 20), 1);
            $decaySeconds = max((int) config('auth.login_rate_limit.decay_seconds', 900), 60);
            $decayMinutes = max((int) ceil($decaySeconds / 60), 1);
            $email = Str::lower((string) $request->input('email', ''));

            $throttledResponse = function (Request $request, array $headers) {
                $retryAfter = (int) ($headers['Retry-After'] ?? 0);

                return response()->json([
                    'message' => $retryAfter > 0
                        ? "Demasiados intentos de inicio de sesion. Intenta nuevamente en {$retryAfter} segundos."
                        : 'Demasiados intentos de inicio de sesion. Intenta nuevamente mas tarde.',
                ], 429, $headers);
            };

            return [
                Limit::perMinutes($decayMinutes, $ipMaxAttempts)
                    ->by('login-ip:'.$request->ip())
                    ->response($throttledResponse),
                Limit::perMinutes($decayMinutes, $maxAttempts)
                    ->by('login-account:'.($email ?: $request->ip()))
                    ->response($throttledResponse),
            ];
        });

        // Balance assistant: every call is a paid model request.
        RateLimiter::for('asistente', function (Request $request) {
            $id = optional($request->user())->id ?: $request->ip();
            $response = fn (Request $request, array $headers) => response()->json([
                'message' => 'Hiciste demasiadas consultas al asistente. Intenta nuevamente más tarde.',
            ], 429, $headers);

            return [
                Limit::perMinute(20)->by('asistente-min:'.$id)->response($response),
                Limit::perDay(200)->by('asistente-dia:'.$id)->response($response),
            ];
        });

        // forgot-password / reset-password: stops mail flooding and token guessing.
        RateLimiter::for('auth-reset', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));
            $throttledResponse = fn (Request $request, array $headers) => response()->json([
                'message' => 'Demasiados intentos. Intenta nuevamente mas tarde.',
            ], 429, $headers);

            return [
                Limit::perMinutes(15, 20)->by('reset-ip:'.$request->ip())->response($throttledResponse),
                Limit::perMinutes(15, 5)->by('reset-account:'.($email ?: $request->ip()))->response($throttledResponse),
            ];
        });
    }
}
