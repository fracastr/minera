<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasBalancePermission
{
    public function handle(Request $request, Closure $next, $permission = 'read')
    {
        $user = $request->user();

        if (!$user || !$user->isActive()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $allowedRoles = [
            'read' => ['admin', 'operator', 'viewer'],
            'write' => ['admin', 'operator'],
        ];

        if (!in_array($user->role, $allowedRoles[$permission] ?? [], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
