<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-group schedule visibility cutoff. A guest sees only the schedule stations
 * whose starts_at is at/after their group's cutoff — e.g. work colleagues
 * invited from 19:00 never see the 12:00 registry office (including its
 * address). NULL means "sees every station" (family, close friends).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->time('schedule_visible_from')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('schedule_visible_from');
        });
    }
};
