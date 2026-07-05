<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('ambassade_id')->constrained('ambassades');
            $table->foreignId('transporteur_id')->constrained('transporteurs');
            $table->enum('statut', [
                'brouillon', 'valide', 'expedie',
                'recu', 'recu_partiel', 'anomalie'
            ])->default('brouillon');
            $table->date('date_expedition')->nullable();
            $table->date('date_reception_prevue')->nullable();
            $table->timestamp('date_reception_effective')->nullable();
            $table->string('qr_token', 512)->unique()->nullable();
            $table->string('bordereau_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('statut');
            $table->index('ambassade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
