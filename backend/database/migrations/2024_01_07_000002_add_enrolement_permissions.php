<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Insérer les permissions du module enrolement
        $permIds = [];
        foreach ([
            ['enrolement', 'view',           'Voir les enrôlements'],
            ['enrolement', 'create',          'Créer un enrôlement'],
            ['enrolement', 'assign_numero',   'Assigner un numéro de passeport'],
        ] as [$module, $action, $description]) {
            $name = "{$module}.{$action}";

            // Éviter les doublons
            $existing = DB::table('permissions')->where('name', $name)->first();
            if ($existing) {
                $permIds[$action] = $existing->id;
                continue;
            }

            $id = DB::table('permissions')->insertGetId([
                'name'        => $name,
                'module'      => $module,
                'action'      => $action,
                'description' => $description,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $permIds[$action] = $id;
        }

        // Associer aux rôles concernés
        $rolesToPerms = [
            'agent_enrolement' => ['view', 'create'],
            'agent_impression' => ['view', 'assign_numero'],
            // Les admins et gestionnaires ont déjà tout via passeports — on leur ajoute aussi
            'super_admin'      => ['view', 'create', 'assign_numero'],
            'admin_mae'        => ['view', 'create', 'assign_numero'],
            'admin_central'    => ['view', 'create', 'assign_numero'],
            'gestionnaire'     => ['view', 'create', 'assign_numero'],
            'responsable_cellule' => ['view', 'create', 'assign_numero'],
            'superviseur'      => ['view'],
            'auditeur'         => ['view'],
        ];

        foreach ($rolesToPerms as $roleName => $actions) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if (! $role) continue;

            foreach ($actions as $action) {
                if (! isset($permIds[$action])) continue;
                $exists = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permIds[$action])
                    ->exists();
                if (! $exists) {
                    DB::table('role_permissions')->insert([
                        'role_id'       => $role->id,
                        'permission_id' => $permIds[$action],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('module', 'enrolement')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->where('module', 'enrolement')->delete();
    }
};
