<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('color_accent', 7)->nullable()->after('color_secondary');
            $table->string('color_background', 7)->nullable()->after('color_accent');
            $table->string('color_card', 7)->nullable()->after('color_background');
        });

        // Bestehende Daten migrieren: primary → accent, secondary → background
        DB::statement("
            UPDATE events
            SET
                color_accent     = COALESCE(color_primary, '#7c2d3e'),
                color_background = COALESCE(color_secondary, '#e8e3de'),
                color_card       = '#ffffff'
            WHERE color_accent IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['color_accent', 'color_background', 'color_card']);
        });
    }
};
