<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Table de notifications unifiée (multicanal : in_app, email, sms).
     * notifiable_type + notifiable_id = cible (toujours User pour l'instant).
     *
     * Types : expedition_validee | expedition_expediee | lot_recu |
     *         lot_recu_partiel | anomalie_signalee | anomalie_resolue |
     *         passeport_livre | alerte_systeme
     *
     * Canaux : in_app | email | sms
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100);
            $table->string('channel', 20)->default('in_app');
            $table->string('notifiable_type', 60);
            $table->unsignedBigInteger('notifiable_id');
            $table->string('title', 200);
            $table->text('message');
            $table->jsonb('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_msg')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('type');
            $table->index('channel');
            $table->index('read_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
