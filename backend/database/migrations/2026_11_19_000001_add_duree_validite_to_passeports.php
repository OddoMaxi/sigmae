<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passeports', function (Blueprint $table) {
            // Durée de validité choisie à l'enrôlement, en années (5 ou 10).
            $table->unsignedTinyInteger('duree_validite')->nullable()->after('date_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('passeports', function (Blueprint $table) {
            $table->dropColumn('duree_validite');
        });
    }
};
