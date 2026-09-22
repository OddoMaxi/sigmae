<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check CHECK (role IN (
                    'super_admin',
                    'admin_central',
                    'responsable_cellule',
                    'agent_enrolement',
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
                'super_admin', 'admin_central', 'responsable_cellule', 'agent_enrolement',
                'agent_reception', 'agent_logistique', 'utilisateur_ambassade', 'auditeur',
                'admin_mae', 'gestionnaire', 'superviseur', 'agent_ambassade',
            ])->default('gestionnaire')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

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
};
