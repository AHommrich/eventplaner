<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0.4 — snapshot the uploader's role on each photo.
 *
 * The photo badge used to be derived dynamically as
 * `uploader_user_id !== event.user_id ? co_organizer : owner`, which only knows
 * "primary owner vs. everyone else" — so a co-owner (e.g. Tabea) would wrongly
 * show as "Mitveranstalter". Storing the role at upload time makes the badge
 * auditable (history stays true even after a role later changes) and correct for
 * co-owners.
 *
 * Allowed values mirror the eventual `roleOn()` union:
 * `owner | event_admin | event_manager | superadmin` (nullable = guest upload,
 * no badge). At this P0 stage the pivot `role` column and `roleOn()` do not exist
 * yet, so writes + backfill use the primary-owner heuristic (owner vs.
 * event_manager — the P1 pivot default). P1 replaces the write with `roleOn()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('uploader_role', 32)->nullable()->after('uploader_user_id');
        });

        // Backfill user uploads via the primary-owner heuristic; guest uploads stay null.
        DB::table('photos')
            ->join('events', 'photos.event_id', '=', 'events.id')
            ->whereNotNull('photos.uploader_user_id')
            ->whereNull('photos.guest_id')
            ->update([
                'photos.uploader_role' => DB::raw(
                    'CASE WHEN photos.uploader_user_id = events.user_id THEN "owner" ELSE "event_manager" END'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('uploader_role');
        });
    }
};
