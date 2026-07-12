<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the single per-group time cutoff (groups.schedule_visible_from) with
 * an explicit per-group/per-station "hidden" set. This lifts the contiguous
 * "from time X" limitation: a group can now be excluded from arbitrary stations
 * (e.g. invited to the ceremony and the party but not the lunch in between).
 *
 * Semantics: empty set = the group sees every station. A row (group, station)
 * hides that one station from that group. New stations and new groups therefore
 * default to fully visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_schedule_item_hidden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('schedule_item_id')->constrained('schedule_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'schedule_item_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('schedule_visible_from');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->time('schedule_visible_from')->nullable()->after('name');
        });

        Schema::dropIfExists('group_schedule_item_hidden');
    }
};
