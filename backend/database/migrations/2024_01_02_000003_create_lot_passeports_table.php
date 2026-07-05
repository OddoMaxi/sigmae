<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_passeports', function (Blueprint $table) {
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->foreignId('passeport_id')->constrained('passeports')->cascadeOnDelete();
            $table->enum('statut_reception', ['en_attente', 'confirme', 'anomalie'])->default('en_attente');
            $table->timestamp('confirme_at')->nullable();
            $table->foreignId('confirme_by')->nullable()->constrained('users')->nullOnDelete();
            $table->primary(['lot_id', 'passeport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_passeports');
    }
};
