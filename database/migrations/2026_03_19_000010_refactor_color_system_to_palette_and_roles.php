<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Neue Spalten hinzufügen
        Schema::table('events', function (Blueprint $table) {
            $table->string('color_tertiary', 7)->nullable()->after('color_secondary');
            $table->string('role_screen_bg',        10)->nullable()->after('color_tertiary');
            $table->string('role_card_bg',          10)->nullable()->after('role_screen_bg');
            $table->string('role_card_text',        10)->nullable()->after('role_card_bg');
            $table->string('role_card_button',      10)->nullable()->after('role_card_text');
            $table->string('role_card_button_text', 10)->nullable()->after('role_card_button');
            $table->string('role_tab_tint',         10)->nullable()->after('role_card_button_text');
            $table->string('role_border',           10)->nullable()->after('role_tab_tint');
            $table->string('role_fab',              10)->nullable()->after('role_border');
        });

        // 2. Daten migrieren — bestehende Farbwerte als Palette übernehmen, Rollen mit sinnvollen Defaults
        DB::statement("
            UPDATE events SET
                color_primary   = COALESCE(NULLIF(color_primary,   ''), color_accent,     '#7c2d3e'),
                color_secondary = COALESCE(NULLIF(color_secondary, ''), color_background, '#e8e3de'),
                color_tertiary  = COALESCE(NULLIF(color_tertiary,  ''), color_card,       '#ffffff'),
                role_screen_bg       = 'secondary',
                role_card_bg         = 'tertiary',
                role_card_text       = 'primary',
                role_card_button     = 'primary',
                role_card_button_text = 'tertiary',
                role_tab_tint        = 'primary',
                role_border          = 'primary',
                role_fab             = 'primary'
        ");

        // 3. Alte Spalten entfernen
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'color_accent',
                'color_background',
                'color_card',
                'color_card_text',
                'color_card_button',
                'color_card_button_text',
                'color_tab_tint',
            ]);
        });
    }

    public function down(): void
    {
        // Alte Spalten wiederherstellen
        Schema::table('events', function (Blueprint $table) {
            $table->string('color_accent', 7)->nullable();
            $table->string('color_background', 7)->nullable();
            $table->string('color_card', 7)->nullable();
            $table->string('color_card_text', 7)->nullable();
            $table->string('color_card_button', 7)->nullable();
            $table->string('color_card_button_text', 7)->nullable();
            $table->string('color_tab_tint', 7)->nullable();
        });

        // Daten zurückmigrieren
        DB::statement("
            UPDATE events SET
                color_accent     = color_primary,
                color_card       = color_secondary,
                color_background = color_tertiary
        ");

        // Neue Spalten entfernen
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'color_tertiary',
                'role_screen_bg', 'role_card_bg', 'role_card_text',
                'role_card_button', 'role_card_button_text',
                'role_tab_tint', 'role_border', 'role_fab',
            ]);
        });
    }
};
