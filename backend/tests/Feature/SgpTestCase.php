<?php

namespace Tests\Feature;

use App\Models\Ambassade;
use App\Models\Lot;
use App\Models\Passeport;
use App\Models\Permission;
use App\Models\Pays;
use App\Models\Role;
use App\Models\Transporteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Classe de base pour les tests SGP-GE.
 * Fournit des helpers pour créer rapidement des utilisateurs, lots, passeports, etc.
 */
abstract class SgpTestCase extends TestCase
{
    use RefreshDatabase;

    protected Pays $pays;
    protected Ambassade $ambassade;
    protected Ambassade $autreAmbassade;
    protected Transporteur $transporteur;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions de base
        $this->seedPermissions();

        // Données de référence
        $this->pays = Pays::create(['nom' => 'France', 'code_iso' => 'FR']);
        $paysSN     = Pays::create(['nom' => 'Sénégal', 'code_iso' => 'SN']);

        $this->ambassade = Ambassade::create([
            'nom'       => 'Ambassade de Guinée à Paris',
            'code'      => 'FR-PAR',
            'ville'     => 'Paris',
            'pays'      => 'France',
            'pays_id'   => $this->pays->id,
            'email_contact' => 'paris@diplomatie.gov.gn',
        ]);

        $this->autreAmbassade = Ambassade::create([
            'nom'    => 'Ambassade de Guinée à Dakar',
            'code'   => 'SN-DKR',
            'ville'  => 'Dakar',
            'pays'   => 'Sénégal',
            'pays_id'=> $paysSN->id,
            'email_contact' => 'dakar@diplomatie.gov.gn',
        ]);

        $this->transporteur = Transporteur::create([
            'nom'       => 'DHL Express',
            'type'      => 'express',
            'telephone' => '+33 1 00 00 00 00',
            'email'     => 'contact@dhl.fr',
            'is_active' => true,
        ]);
    }

    // ── Helpers utilisateurs ──────────────────────────────────────────────────

    protected function makeUser(string $role, array $extra = []): User
    {
        $roleModel = $this->ensureRole($role);
        return User::create(array_merge([
            'name'         => "Test {$role}",
            'email'        => "{$role}_" . uniqid() . "@test.com",
            'password'     => Hash::make('password'),
            'role'         => $role,
            'role_id'      => $roleModel->id,
            'ambassade_id' => null,
            'is_active'    => true,
        ], $extra));
    }

    protected function makeAdminCentral(): User   { return $this->makeUser('admin_central'); }
    protected function makeResponsable(): User    { return $this->makeUser('responsable_cellule'); }
    protected function makeAgentLogistique(): User{ return $this->makeUser('agent_logistique'); }
    protected function makeAuditeur(): User       { return $this->makeUser('auditeur'); }

    protected function makeAgentImpression(): User
    {
        return $this->makeUser('agent_impression');
    }

    protected function makeAgentEnrolement(Ambassade $amb): User
    {
        return $this->makeUser('agent_enrolement', ['ambassade_id' => $amb->id]);
    }

    protected function makeAgentReception(Ambassade $amb): User
    {
        return $this->makeUser('agent_reception', ['ambassade_id' => $amb->id]);
    }

    protected function makeUserAmbassade(Ambassade $amb): User
    {
        return $this->makeUser('utilisateur_ambassade', ['ambassade_id' => $amb->id]);
    }

    // ── Helpers lots & passeports ─────────────────────────────────────────────

    protected function makeLot(User $user, Ambassade $amb, array $extra = []): Lot
    {
        return Lot::create(array_merge([
            'reference'      => Lot::generateReference(),
            'ambassade_id'   => $amb->id,
            'transporteur_id'=> $this->transporteur->id,
            'statut'         => Lot::STATUT_BROUILLON,
            'created_by'     => $user->id,
        ], $extra));
    }

    protected function makePasseport(Ambassade $amb, array $extra = []): Passeport
    {
        $uid = strtoupper(substr(md5(uniqid('', true)), 0, 7));
        return Passeport::create(array_merge([
            'numero'                    => 'PA' . $uid,
            'nom_titulaire'             => 'Diallo',
            'prenom_titulaire'          => 'Alpha',
            'date_naissance'            => '1990-01-01',
            'statut'                    => 'en_stock',
            'ambassade_destination_id'  => $amb->id,
            'pays_destination_id'       => $this->pays->id,
            'email_citoyen'             => strtolower($uid) . '@test.com',
        ], $extra));
    }

    // ── Helpers roles & permissions ───────────────────────────────────────────

    protected function ensureRole(string $roleName): Role
    {
        return Role::firstOrCreate(['name' => $roleName], [
            'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
        ]);
    }

    protected function seedPermissions(): void
    {
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.toggle_status',
            'roles.view', 'roles.manage',
            'pays.view', 'pays.create', 'pays.update', 'pays.delete',
            'ambassades.view', 'ambassades.create', 'ambassades.update',
            'transporteurs.view', 'transporteurs.create', 'transporteurs.update', 'transporteurs.delete',
            'passeports.view', 'passeports.create', 'passeports.update', 'passeports.import',
            'lots.view', 'lots.create', 'lots.update', 'lots.delete',
            'lots.validate', 'lots.ship', 'lots.receive',
            'anomalies.view', 'anomalies.create', 'anomalies.update', 'anomalies.resolve',
            'reporting.view', 'reporting.export',
            'audit.view',
        ];

        $allPermissions = [];
        foreach ($permissions as $perm) {
            [$module, $action] = explode('.', $perm, 2);
            $allPermissions[] = Permission::firstOrCreate(
                ['name' => $perm],
                ['module' => $module, 'action' => $action]
            );
        }

        // Matrice rôle → permissions
        $matrix = [
            'super_admin' => $permissions,
            'admin_central' => array_filter($permissions, fn($p) => !in_array($p, ['roles.manage'])),
            'responsable_cellule' => [
                'ambassades.view', 'pays.view', 'transporteurs.view',
                'passeports.view', 'passeports.create', 'passeports.update', 'passeports.import',
                'lots.view', 'lots.create', 'lots.update', 'lots.delete', 'lots.validate',
                'anomalies.view', 'anomalies.create', 'anomalies.update', 'anomalies.resolve',
                'reporting.view', 'reporting.export',
            ],
            'agent_logistique' => [
                'ambassades.view', 'pays.view',
                'transporteurs.view', 'transporteurs.create', 'transporteurs.update',
                'passeports.view',
                'lots.view', 'lots.create', 'lots.update', 'lots.ship',
                'anomalies.view', 'anomalies.create',
                'reporting.view',
            ],
            'agent_enrolement' => [
                'ambassades.view', 'pays.view',
                'passeports.view', 'passeports.create',
            ],
            'agent_impression' => [
                'ambassades.view', 'pays.view',
                'passeports.view', 'passeports.create',
            ],
            'agent_reception' => [
                'ambassades.view', 'pays.view',
                'passeports.view',
                'lots.view', 'lots.receive',
                'anomalies.view', 'anomalies.create',
            ],
            'utilisateur_ambassade' => [
                'ambassades.view', 'pays.view',
                'passeports.view',
                'lots.view',
                'anomalies.view', 'anomalies.create',
            ],
            'auditeur' => [
                'ambassades.view', 'pays.view', 'transporteurs.view',
                'passeports.view', 'lots.view', 'anomalies.view',
                'reporting.view', 'reporting.export', 'audit.view', 'roles.view',
            ],
        ];

        foreach ($matrix as $roleName => $perms) {
            $role = $this->ensureRole($roleName);
            $ids  = Permission::whereIn('name', $perms)->pluck('id')->all();
            $role->permissions()->sync($ids);
        }
    }

    protected function actingAsRole(string $role, ?Ambassade $ambassade = null): User
    {
        $user = $this->makeUser($role, ['ambassade_id' => $ambassade?->id]);
        $this->actingAs($user, 'sanctum');
        return $user;
    }
}
