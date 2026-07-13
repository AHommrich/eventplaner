<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule stations for an event's timeline (registry office, lunch, party, …).
 * Replaces the free-text events.schedule for structured, per-station display in
 * the guest app. Each station carries an optional structured location that
 * mirrors the events.venue_* columns, so the reused LocationPicker maps 1:1.
 *
 * `starts_at` is a time-of-day; the absolute moment is composed with the
 * event date in the app. Per-group visibility is driven by
 * groups.schedule_visible_from (added in a sibling migration): a guest only
 * sees stations whose time is at/after their group's cutoff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->time('starts_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            // Structured location (mirrors events.venue_* — nullable throughout).
            $table->string('location_name')->nullable();
            $table->string('location_street')->nullable();
            $table->string('location_house_number', 20)->nullable();
            $table->string('location_postal_code', 20)->nullable();
            $table->string('location_city')->nullable();
            $table->string('location_state')->nullable();
            $table->string('location_country', 100)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();

            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};
