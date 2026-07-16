<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0.5 — harden user deletion.
 *
 * `events.user_id` was `ON DELETE CASCADE`, so deleting a user who owned an
 * event silently cascade-deleted that whole event (guests, photos, drink logs)
 * — and, once co-ownership lands, would strip equal co-owners of it too.
 *
 * Switch the FK to RESTRICT: a primary owner can no longer be deleted while
 * they own an event. Events are removed only through the explicit
 * event-delete path, whose own child cascades stay intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
