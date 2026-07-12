<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional end time per station. With both start and end, the guest app
 * can tell whether a station is upcoming (count down to start), running now
 * (start reached, end not yet) or over (count down to the next station). An
 * open-ended station (e.g. the party) simply leaves ends_at null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_items', function (Blueprint $table) {
            $table->time('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_items', function (Blueprint $table) {
            $table->dropColumn('ends_at');
        });
    }
};
