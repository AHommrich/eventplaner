<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drink_logs', function (Blueprint $table) {
            $table->integer('base_points')->default(0)->after('drink_id');
            $table->integer('final_points')->default(0)->after('base_points');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('drink_game_end_time')->nullable()->after('drink_game_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('drink_logs', function (Blueprint $table) {
            $table->dropColumn(['base_points', 'final_points']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('drink_game_end_time');
        });
    }
};
