<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['manquant', 'endommage', 'errone', 'autre']);
            $table->foreignId('passeport_id')->nullable()->constrained('passeports')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->text('description');
            $table->enum('statut', ['ouvert', 'en_traitement', 'resolu'])->default('ouvert');
            $table->foreignId('signale_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolu_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolu_at')->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
