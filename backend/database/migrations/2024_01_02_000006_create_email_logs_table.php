<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passeport_id')->nullable()->constrained('passeports')->nullOnDelete();
            $table->string('recipient', 200);
            $table->string('sujet', 300)->nullable();
            $table->enum('statut', ['envoye', 'echec'])->default('envoye');
            $table->text('error_msg')->nullable();
            $table->timestamp('sent_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
