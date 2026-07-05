<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\User;

class PermissionsTest extends SgpTestCase
{
    // ── Matrice de permissions par rôle ───────────────────────────────────────

    /**
     * @dataProvider rolesPouvantVoirLots
     */
    public function test_role_peut_voir_les_lots(string $role): void
    {
        if (in_array($role, ['agent_reception', 'utilisateur_ambassade'])) {
            $this->actingAsRole($role, $this->ambassade);
        } else {
            $this->actingAsRole($role);
        }

        $this->getJson('/api/lots')->assertOk();
    }

    public static function rolesPouvantVoirLots(): array
    {
        return [
            'admin_central'         => ['admin_central'],
            'responsable_cellule'   => ['responsable_cellule'],
            'agent_logistique'      => ['agent_logistique'],
            'agent_reception'       => ['agent_reception'],
            'utilisateur_ambassade' => ['utilisateur_ambassade'],
            'auditeur'              => ['auditeur'],
        ];
    }

    /**
     * @dataProvider rolesNePouvanPasExpedier
     */
    public function test_role_ne_peut_pas_expedier_lot(string $role): void
    {
        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade, ['statut' => 'valide']);

        if (in_array($role, ['agent_reception', 'utilisateur_ambassade'])) {
            $this->actingAsRole($role, $this->ambassade);
        } else {
            $this->actingAsRole($role);
        }

        $this->postJson("/api/lots/{$lot->id}/expedier")->assertForbidden();
    }

    public static function rolesNePouvanPasExpedier(): array
    {
        return [
            'responsable_cellule'   => ['responsable_cellule'],
            'agent_reception'       => ['agent_reception'],
            'utilisateur_ambassade' => ['utilisateur_ambassade'],
            'auditeur'              => ['auditeur'],
        ];
    }

    // ── Utilisateur ambassade scopé ───────────────────────────────────────────

    public function test_utilisateur_ambassade_voit_seulement_ses_lots(): void
    {
        $admin    = $this->makeAdminCentral();
        $lotA     = $this->makeLot($admin, $this->ambassade);
        $lotB     = $this->makeLot($admin, $this->autreAmbassade);

        $this->actingAsRole('utilisateur_ambassade', $this->ambassade);

        $data = $this->getJson('/api/lots')->json();
        $ids  = collect($data['data'])->pluck('id')->all();

        $this->assertContains($lotA->id, $ids);
        $this->assertNotContains($lotB->id, $ids);
    }

    public function test_agent_reception_autre_ambassade_ne_peut_pas_confirmer(): void
    {
        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade, ['statut' => 'expedie']);

        // Agent de l'autre ambassade
        $this->actingAsRole('agent_reception', $this->autreAmbassade);

        $this->getJson('/api/reception/detail/invalid_token')
            ->assertStatus(400); // Token invalide, pas 403
    }

    // ── Auditeur : lecture seule ──────────────────────────────────────────────

    public function test_auditeur_peut_voir_audit_logs(): void
    {
        $this->actingAsRole('auditeur');
        $this->getJson('/api/audit')->assertOk();
    }

    public function test_auditeur_ne_peut_pas_creer_passeport(): void
    {
        $this->actingAsRole('auditeur');
        $this->postJson('/api/passeports', [
            'numero'         => 'PA9999999',
            'nom_titulaire'  => 'Test',
            'prenom_titulaire'=> 'User',
            'date_naissance' => '1990-01-01',
            'ambassade_destination_id' => $this->ambassade->id,
            'pays_destination_id'      => $this->pays->id,
        ])->assertForbidden();
    }

    public function test_auditeur_ne_peut_pas_acceder_gestion_utilisateurs(): void
    {
        $this->actingAsRole('auditeur');
        $this->postJson('/api/users', [
            'name'     => 'New User',
            'email'    => 'new@test.com',
            'password' => 'password123',
            'role'     => 'auditeur',
        ])->assertForbidden();
    }

    // ── Super admin : accès total ─────────────────────────────────────────────

    public function test_super_admin_acces_toutes_ressources(): void
    {
        $this->actingAsRole('super_admin');

        $this->getJson('/api/lots')->assertOk();
        $this->getJson('/api/passeports')->assertOk();
        $this->getJson('/api/audit')->assertOk();
        $this->getJson('/api/reporting/dashboard')->assertOk();
    }
}
