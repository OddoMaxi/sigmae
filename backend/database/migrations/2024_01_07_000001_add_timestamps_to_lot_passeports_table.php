<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lot_passeports ADD COLUMN IF NOT EXISTS created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
        DB::statement('ALTER TABLE lot_passeports ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lot_passeports DROP COLUMN IF EXISTS created_at');
        DB::statement('ALTER TABLE lot_passeports DROP COLUMN IF EXISTS updated_at');
    }
};
