<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasDashboardAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isActive()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$user->isAdmin() && !$user->dashboard_access) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
