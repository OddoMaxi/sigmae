<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Lots : commentaire de l'ambassade à la réception ──────────────
        Schema::table('lots', function (Blueprint $table) {
            $table->text('commentaire_ambassade')->nullable()->after('notes');
        });

        // ── 2. Passeports : timestamp de mise en disponibilité ───────────────
        Schema::table('passeports', function (Blueprint $table) {
            $table->timestamp('disponible_at')->nullable()->after('dispatched_at');
        });

        // ── 3. lot_passeports : ajout du statut "manquant" ───────────────────
        DB::statement('ALTER TABLE lot_passeports DROP CONSTRAINT IF EXISTS lot_passeports_statut_reception_check');
        DB::statement(
            "ALTER TABLE lot_passeports ADD CONSTRAINT lot_passeports_statut_reception_check
             CHECK (statut_reception IN ('en_attente','confirme','anomalie','manquant'))"
        );
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('commentaire_ambassade');
        });

        Schema::table('passeports', function (Blueprint $table) {
            $table->dropColumn('disponible_at');
        });

        DB::statement('ALTER TABLE lot_passeports DROP CONSTRAINT IF EXISTS lot_passeports_statut_reception_check');
        DB::statement(
            "ALTER TABLE lot_passeports ADD CONSTRAINT lot_passeports_statut_reception_check
             CHECK (statut_reception IN ('en_attente','confirme','anomalie'))"
        );
    }
};
