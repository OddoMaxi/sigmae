<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * PostgreSQL stocke les enum Laravel comme varchar + CHECK constraint.
     * On remplace le constraint pour accepter les 7 nouveaux rôles tout en
     * conservant les anciens slugs (backward-compat pendant la transition).
     * Sur SQLite (tests), pas de DROP/ADD CONSTRAINT : on redéfinit la colonne
     * enum directement via le Schema builder.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
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

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'admin_central', 'responsable_cellule',
                'agent_reception', 'agent_logistique', 'utilisateur_ambassade', 'auditeur',
                'admin_mae', 'gestionnaire', 'superviseur', 'agent_ambassade',
            ])->default('gestionnaire')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check CHECK (role IN (
                    'admin_mae','gestionnaire','superviseur','agent_ambassade'
                ))
            ");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin_mae', 'gestionnaire', 'superviseur', 'agent_ambassade'])
                  ->default('gestionnaire')
                  ->change();
        });
    }
};
