<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ambassades', function (Blueprint $table) {
            $table->foreignId('pays_id')
                  ->nullable()
                  ->after('ville')
                  ->constrained('pays')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ambassades', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Pays::class);
            $table->dropColumn('pays_id');
        });
    }
};
