<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-guest content hide: a guest hides everything uploaded by another guest
 * of the same event. Owner uploads (photos.guest_id IS NULL) are structurally
 * out of scope — the hidden_guest_id FK only accepts a guests row.
 *
 * event_id is denormalised so the same-event guard and cross-event filters
 * on /api/photos can skip the guest lookup on every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_content_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('viewer_guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('hidden_guest_id')->constrained('guests')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['viewer_guest_id', 'hidden_guest_id']);
            $table->index(['event_id', 'viewer_guest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_content_hides');
    }
};
