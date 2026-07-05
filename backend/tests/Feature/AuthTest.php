<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends SgpTestCase
{
    // ── Connexion ─────────────────────────────────────────────────────────────

    public function test_login_avec_identifiants_valides(): void
    {
        $user = $this->makeAdminCentral();
        $user->update(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_avec_mauvais_mot_de_passe(): void
    {
        $user = $this->makeAdminCentral();

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    public function test_login_utilisateur_inactif_refuse(): void
    {
        $user = $this->makeAdminCentral();
        $user->update(['is_active' => false, 'password' => Hash::make('password')]);

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_me_retourne_utilisateur_connecte(): void
    {
        $user = $this->makeAdminCentral();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_acces_route_protegee_sans_token(): void
    {
        $this->getJson('/api/lots')->assertUnauthorized();
        $this->getJson('/api/passeports')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_invalide_le_token(): void
    {
        $user = $this->makeAdminCentral();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/auth/logout')->assertOk();

        // Après logout, le user ne devrait plus avoir de tokens actifs
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
