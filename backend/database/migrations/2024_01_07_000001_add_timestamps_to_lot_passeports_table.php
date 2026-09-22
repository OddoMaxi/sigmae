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
            DB::statement('ALTER TABLE lot_passeports ADD COLUMN IF NOT EXISTS created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
            DB::statement('ALTER TABLE lot_passeports ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');

            return;
        }

        Schema::table('lot_passeports', function (Blueprint $table) {
            if (! Schema::hasColumn('lot_passeports', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (! Schema::hasColumn('lot_passeports', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lot_passeports', function (Blueprint $table) {
            if (Schema::hasColumn('lot_passeports', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('lot_passeports', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
