<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
     * PostgreSQL stocke les enum Laravel comme varchar + CHECK constraint.
     * On remplace le constraint pour accepter les 7 nouveaux rôles tout en
     * conservant les anciens slugs (backward-compat pendant la transition).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check CHECK (role IN (
                'super_admin',
                'admin_central',
                'responsable_cellule',
                'agent_reception',
                'agent_logistique',
                'utilisateur_ambassade',
                'auditeur',
                'admin_mae',
                'gestionnaire',
                'superviseur',
                'agent_ambassade'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check CHECK (role IN (
                'admin_mae','gestionnaire','superviseur','agent_ambassade'
            ))
        ");
    }
};
