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
            // Référence unique de la demande (système DGDI / imprimerie)
            $table->string('reference_demande', 50)->nullable()->unique()->after('numero');

            // Destination explicite (avant lot, pour pré-affectation)
            $table->foreignId('pays_destination_id')->nullable()
                  ->after('email_citoyen')
                  ->constrained('pays')->nullOnDelete();
            $table->foreignId('ambassade_destination_id')->nullable()
                  ->after('pays_destination_id')
                  ->constrained('ambassades')->nullOnDelete();

            // Dates de cycle de vie
            $table->date('date_impression')->nullable()->after('ambassade_destination_id');
            $table->date('date_reception_mae')->nullable()->after('date_impression');

            // Index supplémentaires
            $table->index('reference_demande');
            $table->index('pays_destination_id');
            $table->index('ambassade_destination_id');
            $table->index('date_impression');
        });

        // Étendre le CHECK constraint des statuts (PostgreSQL : varchar + CHECK)
        DB::statement('ALTER TABLE passeports DROP CONSTRAINT IF EXISTS passeports_statut_check');
        DB::statement("
            ALTER TABLE passeports
            ADD CONSTRAINT passeports_statut_check CHECK (statut IN (
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
            $table->dropForeign(['pays_destination_id']);
            $table->dropForeign(['ambassade_destination_id']);
            $table->dropColumn([
                'reference_demande', 'pays_destination_id', 'ambassade_destination_id',
                'date_impression', 'date_reception_mae',
            ]);
        });

        DB::statement('ALTER TABLE passeports DROP CONSTRAINT IF EXISTS passeports_statut_check');
        DB::statement("
            ALTER TABLE passeports
            ADD CONSTRAINT passeports_statut_check CHECK (statut IN (
                'en_stock','en_lot','expedie','livre','anomalie'
            ))
        ");
    }
};
