<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /*
     * Accepte un ou plusieurs slugs de rôle séparés par une virgule.
     * Exemple : middleware('role:super_admin,admin_central')
     *
     * Vérifie d'abord le slug enum (User::role) puis le nom du rôle lié (role_id).
     * Cela garantit la compatibilité pendant la coexistence des anciens slugs.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $userRole       = $user->role;
        $linkedRoleName = $user->roleModel?->name;

        $authorized = collect($roles)->contains(
            fn ($r) => $r === $userRole || $r === $linkedRoleName
        );

        if (! $authorized) {
            return response()->json([
                'message' => 'Accès non autorisé.',
                'role'    => $userRole,
            ], 403);
        }

        return $next($request);
    }
}
