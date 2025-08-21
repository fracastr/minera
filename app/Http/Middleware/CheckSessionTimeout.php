<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CheckSessionTimeout
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $lastActivity = Session::get('last_activity');
            $timeout = config('session.lifetime', 120) * 60; // 120 minutos en segundos

            if ($lastActivity && (time() - $lastActivity > $timeout)) {
                Auth::logout();
                Session::flush();

                return redirect()->route('login')
                    ->with('message', 'Su sesión ha expirado por inactividad. Por favor, inicie sesión nuevamente.');
            }

            Session::put('last_activity', time());
        }

        return $next($request);
    }
}
