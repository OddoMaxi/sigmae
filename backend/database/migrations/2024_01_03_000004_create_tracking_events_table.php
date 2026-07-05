<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Événements de suivi immuables (append-only).
     * trackable_type + trackable_id = relation polymorphe vers Lot ou Passeport.
     *
     * Événements lot    : created | validated | shipped | received |
     *                     received_partial | anomaly_detected | updated | cancelled
     * Événements passport: added_to_stock | added_to_lot | removed_from_lot |
     *                     shipped | confirmed | anomaly_reported | delivered
     */
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type', 60);
            $table->unsignedBigInteger('trackable_id');
            $table->string('event', 100);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trackable_type', 'trackable_id']);
            $table->index('event');
            $table->index('created_at');
            $table->index('triggered_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
