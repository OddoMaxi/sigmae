<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Rôles système ────────────────────────────────────────────────────
        $rolesData = [
            ['name' => 'admin_mae',       'display_name' => 'Administrateur MAE',        'description' => 'Accès complet à toutes les fonctionnalités'],
            ['name' => 'gestionnaire',    'display_name' => 'Gestionnaire de passeports', 'description' => 'Gestion des lots, passeports, transporteurs'],
            ['name' => 'superviseur',     'display_name' => 'Superviseur',                'description' => "Consultation, rapports et résolution d'anomalies"],
            ['name' => 'agent_ambassade', 'display_name' => "Agent d'ambassade",          'description' => 'Réception des lots dans les ambassades'],
        ];

        $roles = [];
        foreach ($rolesData as $r) {
            $roles[$r['name']] = Role::firstOrCreate(['name' => $r['name']], $r + ['is_system' => true]);
        }

        // ── Permissions ───────────────────────────────────────────────────────
        // Format : [name, module, action, description]
        $permissionsData = [
            // users
            ['users.view',          'users',       'view',          'Voir la liste des utilisateurs'],
            ['users.create',        'users',       'create',        'Créer un utilisateur'],
            ['users.update',        'users',       'update',        'Modifier un utilisateur'],
            ['users.delete',        'users',       'delete',        'Supprimer un utilisateur'],
            ['users.toggle_status', 'users',       'toggle_status', 'Activer/désactiver un utilisateur'],
            // roles
            ['roles.view',          'roles',       'view',          'Voir les rôles'],
            ['roles.manage',        'roles',       'manage',        'Gérer les rôles et permissions'],
            // ambassades
            ['ambassades.view',     'ambassades',  'view',          'Voir les ambassades'],
            ['ambassades.create',   'ambassades',  'create',        'Créer une ambassade'],
            ['ambassades.update',   'ambassades',  'update',        'Modifier une ambassade'],
            // pays
            ['pays.view',           'pays',        'view',          'Voir les pays'],
            ['pays.create',         'pays',        'create',        'Créer un pays'],
            ['pays.update',         'pays',        'update',        'Modifier un pays'],
            ['pays.delete',         'pays',        'delete',        'Supprimer un pays'],
            // transporteurs
            ['transporteurs.view',   'transporteurs', 'view',   'Voir les transporteurs'],
            ['transporteurs.create', 'transporteurs', 'create', 'Créer un transporteur'],
            ['transporteurs.update', 'transporteurs', 'update', 'Modifier un transporteur'],
            ['transporteurs.delete', 'transporteurs', 'delete', 'Supprimer un transporteur'],
            // passeports
            ['passeports.view',     'passeports',  'view',          'Voir les passeports'],
            ['passeports.create',   'passeports',  'create',        'Créer un passeport'],
            ['passeports.update',   'passeports',  'update',        'Modifier un passeport'],
            ['passeports.import',   'passeports',  'import',        'Importer des passeports (CSV/Excel)'],
            // lots
            ['lots.view',           'lots',        'view',          'Voir les lots'],
            ['lots.create',         'lots',        'create',        'Créer un lot'],
            ['lots.update',         'lots',        'update',        'Modifier un lot'],
            ['lots.delete',         'lots',        'delete',        'Supprimer un lot brouillon'],
            ['lots.validate',       'lots',        'validate',      'Valider un lot (brouillon → validé)'],
            ['lots.ship',           'lots',        'ship',          'Expédier un lot (validé → expédié)'],
            ['lots.receive',        'lots',        'receive',       "Confirmer la réception d'un lot"],
            // anomalies
            ['anomalies.view',      'anomalies',   'view',          'Voir les anomalies'],
            ['anomalies.create',    'anomalies',   'create',        'Signaler une anomalie'],
            ['anomalies.update',    'anomalies',   'update',        'Modifier une anomalie'],
            ['anomalies.resolve',   'anomalies',   'resolve',       'Résoudre une anomalie'],
            // reporting
            ['reporting.view',      'reporting',   'view',          'Accéder aux rapports'],
            ['reporting.export',    'reporting',   'export',        'Exporter les données'],
            // audit
            ['audit.view',          'audit',       'view',          "Voir les journaux d'audit"],
        ];

        $permissions = [];
        foreach ($permissionsData as [$name, $module, $action, $description]) {
            $permissions[$name] = Permission::firstOrCreate(
                ['name' => $name],
                ['module' => $module, 'action' => $action, 'description' => $description]
            );
        }

        // ── Matrice rôle → permissions ────────────────────────────────────────
        $matrix = [
            'admin_mae' => array_keys($permissions), // toutes

            'gestionnaire' => [
                'ambassades.view', 'pays.view',
                'transporteurs.view', 'transporteurs.create', 'transporteurs.update', 'transporteurs.delete',
                'passeports.view', 'passeports.create', 'passeports.update', 'passeports.import',
                'lots.view', 'lots.create', 'lots.update', 'lots.delete', 'lots.validate', 'lots.ship',
                'anomalies.view', 'anomalies.create', 'anomalies.update', 'anomalies.resolve',
                'reporting.view', 'reporting.export',
            ],

            'superviseur' => [
                'ambassades.view', 'pays.view',
                'lots.view', 'passeports.view',
                'anomalies.view', 'anomalies.update', 'anomalies.resolve',
                'reporting.view', 'reporting.export',
                'audit.view',
            ],

            'agent_ambassade' => [
                'ambassades.view', 'pays.view',
                'lots.view', 'lots.receive',
                'passeports.view',
                'anomalies.view', 'anomalies.create',
            ],
        ];

        foreach ($matrix as $roleName => $permNames) {
            $role = $roles[$roleName];
            $ids  = collect($permNames)->map(fn ($n) => $permissions[$n]->id)->all();
            $role->permissions()->syncWithoutDetaching($ids);
        }

        // ── Lier les users existants à leur rôle ──────────────────────────────
        foreach (User::whereNull('role_id')->get() as $user) {
            $role = $roles[$user->role] ?? null;
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }
        }

        $this->command->info('✓ Rôles, permissions et liaisons créés.');
    }
}
