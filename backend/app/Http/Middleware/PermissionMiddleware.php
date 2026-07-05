<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'message'    => 'Permission refusée.',
                'permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
