<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
     * Transforme la table pivot lot_passeports en shipment_items complet :
     *  - ajoute un id bigserial comme nouvelle PK
     *  - remplace la PK composite par une contrainte UNIQUE
     *  - ajoute : notes, position, statut_expedition
     */
    public function up(): void
    {
        // 1. Ajouter la colonne id en tant que séquence PostgreSQL
        DB::statement('ALTER TABLE lot_passeports ADD COLUMN id BIGSERIAL');

        // 2. Supprimer l'ancienne PK composite
        DB::statement('ALTER TABLE lot_passeports DROP CONSTRAINT lot_passeports_pkey');

        // 3. Définir id comme PK
        DB::statement('ALTER TABLE lot_passeports ADD PRIMARY KEY (id)');

        // 4. Garantir l'unicité lot+passeport
        DB::statement('ALTER TABLE lot_passeports ADD CONSTRAINT lot_passeports_lot_id_passeport_id_unique UNIQUE (lot_id, passeport_id)');

        // 5. Ajouter les colonnes métier
        Schema::table('lot_passeports', function (Blueprint $table) {
            $table->smallInteger('position')->nullable()->comment('Ordre dans le lot');
            $table->text('notes')->nullable();
            $table->enum('statut_expedition', ['en_attente', 'inclus', 'exclu'])
                  ->default('inclus')
                  ->after('statut_reception');
        });
    }

    public function down(): void
    {
        Schema::table('lot_passeports', function (Blueprint $table) {
            $table->dropColumn(['position', 'notes', 'statut_expedition']);
        });

        DB::statement('ALTER TABLE lot_passeports DROP CONSTRAINT IF EXISTS lot_passeports_lot_id_passeport_id_unique');
        DB::statement('ALTER TABLE lot_passeports DROP CONSTRAINT IF EXISTS lot_passeports_pkey');
        DB::statement('ALTER TABLE lot_passeports DROP COLUMN IF EXISTS id');
        DB::statement('ALTER TABLE lot_passeports ADD PRIMARY KEY (lot_id, passeport_id)');
    }
};
