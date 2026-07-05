<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez un administrateur.',
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken(
            'sgp-token',
            ['*'],
            now()->addHours(8)
        )->plainTextToken;

        AuditLog::record('auth.login', 'User', $user->id);

        $user->load(['ambassade', 'roleModel.permissions']);

        return response()->json([
            'token'       => $token,
            'token_type'  => 'Bearer',
            'expires_in'  => 8 * 3600,
            'user'        => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', 'User', $request->user()->id);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['ambassade', 'roleModel.permissions']);

        return response()->json($this->formatUser($user));
    }

    public function refresh(Request $request)
    {
        $user = $request->user();

        // Révoque le token courant
        $user->currentAccessToken()->delete();

        $token = $user->createToken(
            'sgp-token',
            ['*'],
            now()->addHours(8)
        )->plainTextToken;

        AuditLog::record('auth.refresh', 'User', $user->id);

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => 8 * 3600,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function formatUser(User $user): array
    {
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'role_label'    => $user->roleModel?->display_name ?? $user->role,
            'ambassade_id'  => $user->ambassade_id,
            'ambassade'     => $user->ambassade,
            'is_active'     => $user->is_active,
            'last_login_at' => $user->last_login_at,
            'permissions'   => $user->roleModel?->permissions->pluck('name') ?? [],
            'permissions_by_module' => $user->roleModel?->permissions
                ->groupBy('module')
                ->map(fn ($perms) => $perms->pluck('action')) ?? [],
        ];
    }
}
