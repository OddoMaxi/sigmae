<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passeports', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->string('nom_titulaire', 100);
            $table->string('prenom_titulaire', 100);
            $table->date('date_naissance')->nullable();
            $table->string('email_citoyen', 200)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->enum('statut', [
                'en_stock', 'en_lot', 'expedie', 'livre', 'anomalie'
            ])->default('en_stock');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('lot_id');
            $table->index('numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passeports');
    }
};
