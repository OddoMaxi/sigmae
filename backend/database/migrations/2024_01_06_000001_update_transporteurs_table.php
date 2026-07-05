<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transporteurs', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->after('nom');
            $table->jsonb('pays_desservis')->nullable()->after('adresse');
            $table->string('lien_suivi', 500)->nullable()->after('pays_desservis');
        });

        // CHECK constraint on type
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE transporteurs
             ADD CONSTRAINT transporteurs_type_check
             CHECK (type IN ('aerien','maritime','routier','courrier','autre'))"
        );
    }

    public function down(): void
    {
        Schema::table('transporteurs', function (Blueprint $table) {
            $table->dropColumn(['type', 'pays_desservis', 'lien_suivi']);
        });
    }
};
