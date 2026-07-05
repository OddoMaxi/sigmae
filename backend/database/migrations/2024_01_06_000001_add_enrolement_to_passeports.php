<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passeports', function (Blueprint $table) {
            // numero devient nullable : à l'enrôlement, le numéro n'est pas encore connu
            $table->string('numero', 50)->nullable()->change();

            // Champs d'enrôlement
            $table->timestamp('enrolled_at')->nullable()->after('date_reception_mae');
            $table->foreignId('enrolled_by')
                  ->nullable()
                  ->after('enrolled_at')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->index('enrolled_by');
        });

        // Ajouter 'enrolee' au CHECK constraint PostgreSQL
        DB::statement('ALTER TABLE passeports DROP CONSTRAINT IF EXISTS passeports_statut_check');
        DB::statement("
            ALTER TABLE passeports
            ADD CONSTRAINT passeports_statut_check CHECK (statut IN (
                'enrolee',
                'imprime',
                'recu_mae',
                'en_stock',
                'en_lot',
                'expedie',
                'en_transit',
                'recu_ambassade',
                'disponible_retrait',
                'remis_citoyen',
                'anomalie',
                'livre'
            ))
        ");
    }

    public function down(): void
    {
        Schema::table('passeports', function (Blueprint $table) {
            $table->string('numero', 50)->nullable(false)->change();
            $table->dropForeign(['enrolled_by']);
            $table->dropColumn(['enrolled_at', 'enrolled_by']);
        });

        DB::statement('ALTER TABLE passeports DROP CONSTRAINT IF EXISTS passeports_statut_check');
        DB::statement("
            ALTER TABLE passeports
            ADD CONSTRAINT passeports_statut_check CHECK (statut IN (
                'imprime','recu_mae','en_stock','en_lot','expedie',
                'en_transit','recu_ambassade','disponible_retrait',
                'remis_citoyen','anomalie','livre'
            ))
        ");
    }
};
