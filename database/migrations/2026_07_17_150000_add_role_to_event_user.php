<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a per-event tier to the event_user pivot.
 *
 * Allowed values: 'owner' | 'event_admin' | 'event_manager'. Ownership itself is
 * anchored on events.user_id (the primary owner); a pivot 'owner' row is a
 * co-owner. Existing pivot rows were full-access co-organizers, so the safe
 * automatic backfill is 'event_manager' (the least-privileged co-organizer tier).
 * Production rows must be reviewed by hand before deploy — a spouse/partner who
 * legitimately needs owner rights should be promoted to 'owner' (see plan §11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_user', function (Blueprint $table) {
            $table->string('role', 32)->default('event_manager')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
