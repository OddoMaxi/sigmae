<?php

namespace Database\Seeders;

use App\Models\Ambassade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    /*
     * Matrice des 9 rôles SGP-GE :
     *
     * super_admin           — accès total, gestion des rôles système
     * admin_central         — administration complète sans modification des rôles système
     * responsable_cellule   — gestion passeports + lots (validation) + anomalies
     * agent_logistique      — préparation/expédition des lots + transporteurs
     * agent_enrolement      — enrôlement biométrique à l'ambassade (premier niveau du circuit)
     * agent_impression      — unité d'impression MAE : assigne les numéros aux enrôlements
     * agent_reception       — réception des lots en ambassade + signalement anomalies
     * utilisateur_ambassade — consultation (scopée à l'ambassade) + signalement
     * auditeur              — lecture seule, audit, reporting
     */

    private array $rolesData = [
        [
            'name'         => 'super_admin',
            'display_name' => 'Super Administrateur',
            'description'  => "Accès total, y compris la gestion des rôles système et la configuration.",
            'is_system'    => true,
        ],
        [
            'name'         => 'admin_central',
            'display_name' => 'Administrateur Central',
            'description'  => "Administration complète des utilisateurs, ambassades et référentiels.",
            'is_system'    => true,
        ],
        [
            'name'         => 'responsable_cellule',
            'display_name' => 'Responsable Cellule',
            'description'  => "Gestion des passeports, validation des lots, résolution des anomalies.",
            'is_system'    => true,
        ],
        [
            'name'         => 'agent_logistique',
            'display_name' => 'Agent Logistique',
            'description'  => "Création et expédition des lots, gestion des transporteurs.",
            'is_system'    => true,
        ],
        [
            'name'         => 'agent_enrolement',
            'display_name' => 'Agent Enrôlement',
            'description'  => "Saisie des demandes de passeport après enrôlement biométrique à l'ambassade.",
            'is_system'    => true,
        ],
        [
            'name'         => 'agent_impression',
            'display_name' => 'Agent Impression',
            'description'  => "Unité d'impression MAE : assigne les numéros de passeport aux dossiers enrôlés.",
            'is_system'    => true,
        ],
        [
            'name'         => 'agent_reception',
            'display_name' => 'Agent Réception',
            'description'  => "Réception des lots en ambassade, confirmation passeport par passeport.",
            'is_system'    => true,
        ],
        [
            'name'         => 'utilisateur_ambassade',
            'display_name' => 'Utilisateur Ambassade',
            'description'  => "Consultation (scopée à son ambassade), signalement des anomalies.",
            'is_system'    => true,
        ],
        [
            'name'         => 'auditeur',
            'display_name' => 'Auditeur',
            'description'  => "Lecture seule : reporting, journaux d'audit, statistiques.",
            'is_system'    => true,
        ],
    ];

    // ── Matrice rôle → permissions ─────────────────────────────────────────────
    private array $matrix = [
        'super_admin' => '*', // toutes

        'admin_central' => [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.toggle_status',
            'roles.view',
            'ambassades.view', 'ambassades.create', 'ambassades.update',
            'pays.view', 'pays.create', 'pays.update', 'pays.delete',
            'transporteurs.view', 'transporteurs.create', 'transporteurs.update', 'transporteurs.delete',
            'passeports.view', 'passeports.create', 'passeports.update', 'passeports.import',
            'lots.view', 'lots.create', 'lots.update', 'lots.delete', 'lots.validate', 'lots.ship', 'lots.receive',
            'anomalies.view', 'anomalies.create', 'anomalies.update', 'anomalies.resolve',
            'reporting.view', 'reporting.export',
            'audit.view',
        ],

        'responsable_cellule' => [
            'ambassades.view', 'pays.view',
            'transporteurs.view',
            'passeports.view', 'passeports.create', 'passeports.update', 'passeports.import',
            'lots.view', 'lots.create', 'lots.update', 'lots.delete', 'lots.validate',
            'anomalies.view', 'anomalies.create', 'anomalies.update', 'anomalies.resolve',
            'reporting.view', 'reporting.export',
        ],

        'agent_logistique' => [
            'ambassades.view', 'pays.view',
            'transporteurs.view', 'transporteurs.create', 'transporteurs.update', 'transporteurs.delete',
            'passeports.view',
            'lots.view', 'lots.create', 'lots.update', 'lots.ship',
            'anomalies.view', 'anomalies.create',
            'reporting.view',
        ],

        'agent_enrolement' => [
            'ambassades.view', 'pays.view',
            'passeports.view', 'passeports.create',
            'enrolement.view', 'enrolement.create',
        ],

        'agent_impression' => [
            'ambassades.view', 'pays.view',
            'passeports.view', 'passeports.create',
            'enrolement.view', 'enrolement.assign_numero',
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
            'ambassades.view', 'pays.view',
            'transporteurs.view',
            'passeports.view',
            'lots.view',
            'anomalies.view',
            'reporting.view', 'reporting.export',
            'audit.view',
            'roles.view',
        ],
    ];

    public function run(): void
    {
        // ── Créer / mettre à jour les 7 rôles ────────────────────────────────
        $roles = [];
        foreach ($this->rolesData as $r) {
            $roles[$r['name']] = Role::updateOrCreate(
                ['name' => $r['name']],
                $r
            );
        }

        // ── Récupérer toutes les permissions ──────────────────────────────────
        $allPermissions = Permission::all()->keyBy('name');

        // ── Assigner les permissions selon la matrice ─────────────────────────
        foreach ($this->matrix as $roleName => $perms) {
            $role = $roles[$roleName] ?? null;
            if (! $role) {
                continue;
            }

            if ($perms === '*') {
                $role->permissions()->sync($allPermissions->pluck('id')->all());
            } else {
                $ids = collect($perms)
                    ->filter(fn ($p) => isset($allPermissions[$p]))
                    ->map(fn ($p) => $allPermissions[$p]->id)
                    ->all();
                $role->permissions()->sync($ids);
            }
        }

        // ── Créer les utilisateurs de test ───────────────────────────────────
        $paris = Ambassade::where('code', 'FR-PAR')->first();
        $dakar = Ambassade::where('code', 'SN-DKR')->first();

        $usersData = [
            [
                'email'        => 'superadmin@mae.gov.gn',
                'name'         => 'Super Administrateur',
                'role'         => 'super_admin',
                'password'     => 'SuperAdmin@2025!',
                'ambassade_id' => null,
            ],
            [
                'email'        => 'admin@mae.gov.gn',
                'name'         => 'Administrateur Central',
                'role'         => 'admin_central',
                'password'     => 'Admin@2025!',
                'ambassade_id' => null,
            ],
            [
                'email'        => 'responsable@mae.gov.gn',
                'name'         => 'Kadiatou Diallo',
                'role'         => 'responsable_cellule',
                'password'     => 'Resp@2025!',
                'ambassade_id' => null,
            ],
            [
                'email'        => 'logistique@mae.gov.gn',
                'name'         => 'Mamadou Kouyaté',
                'role'         => 'agent_logistique',
                'password'     => 'Logis@2025!',
                'ambassade_id' => null,
            ],
            [
                'email'        => 'impression@mae.gov.gn',
                'name'         => 'Boubacar Konaté',
                'role'         => 'agent_impression',
                'password'     => 'Impr@2025!',
                'ambassade_id' => null,
            ],
            [
                'email'        => 'enrolement.paris@diplomatie.gov.gn',
                'name'         => 'Fatoumata Camara',
                'role'         => 'agent_enrolement',
                'password'     => 'Enrol@2025!',
                'ambassade_id' => $paris?->id,
            ],
            [
                'email'        => 'reception.paris@diplomatie.gov.gn',
                'name'         => 'Sékou Diallo',
                'role'         => 'agent_reception',
                'password'     => 'Agent@2025!',
                'ambassade_id' => $paris?->id,
            ],
            [
                'email'        => 'reception.dakar@diplomatie.gov.gn',
                'name'         => 'Aïssatou Bah',
                'role'         => 'agent_reception',
                'password'     => 'Agent@2025!',
                'ambassade_id' => $dakar?->id,
            ],
            [
                'email'        => 'consul.paris@diplomatie.gov.gn',
                'name'         => 'Aminata Sylla',
                'role'         => 'utilisateur_ambassade',
                'password'     => 'Consul@2025!',
                'ambassade_id' => $paris?->id,
            ],
            [
                'email'        => 'auditeur@mae.gov.gn',
                'name'         => 'Ibrahim Barry',
                'role'         => 'auditeur',
                'password'     => 'Audit@2025!',
                'ambassade_id' => null,
            ],
        ];

        foreach ($usersData as $u) {
            $role   = $roles[$u['role']] ?? null;
            $user   = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'         => $u['name'],
                    'password'     => Hash::make($u['password']),
                    'role'         => $u['role'],
                    'role_id'      => $role?->id,
                    'ambassade_id' => $u['ambassade_id'],
                    'is_active'    => true,
                ]
            );

            // Mettre à jour role_id si l'user existait déjà
            if ($user->role_id === null && $role) {
                $user->update(['role_id' => $role->id]);
            }
        }

        // Lier les anciens users (admin_mae, gestionnaire, etc.) à leur rôle
        $legacyMap = [
            'admin_mae'      => $roles['admin_central'] ?? null,
            'gestionnaire'   => $roles['responsable_cellule'] ?? null,
            'superviseur'    => $roles['auditeur'] ?? null,
            'agent_ambassade'=> $roles['agent_reception'] ?? null,
        ];

        foreach ($legacyMap as $oldRole => $newRoleModel) {
            if ($newRoleModel) {
                User::where('role', $oldRole)->whereNull('role_id')
                    ->update(['role_id' => $newRoleModel->id]);
            }
        }

        $this->command->info('✓ 9 rôles, permissions et 10 utilisateurs de test créés.');
        $this->command->table(
            ['Email', 'Rôle', 'Mot de passe'],
            collect($usersData)->map(fn ($u) => [$u['email'], $u['role'], $u['password']])->all()
        );
    }
}
