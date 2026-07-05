<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 200);
            $table->string('contact', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 200)->nullable();
            $table->text('adresse')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporteurs');
    }
};
