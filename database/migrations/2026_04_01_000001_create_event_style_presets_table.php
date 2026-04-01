<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_style_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Palette
            $table->string('color_primary', 7)->nullable();
            $table->string('color_secondary', 7)->nullable();
            $table->string('color_tertiary', 7)->nullable();
            $table->string('color_home_text', 7)->nullable();
            $table->string('color_home_shadow', 7)->nullable();
            $table->unsignedTinyInteger('home_shadow_opacity')->nullable();
            // Rollen
            $table->string('role_screen_bg', 20)->nullable();
            $table->string('role_card_bg', 20)->nullable();
            $table->string('role_card_text', 20)->nullable();
            $table->string('role_card_button', 20)->nullable();
            $table->string('role_card_button_text', 20)->nullable();
            $table->string('role_tab_tint', 20)->nullable();
            $table->string('role_border', 20)->nullable();
            $table->string('role_fab', 20)->nullable();
            $table->string('role_fab_icon', 20)->nullable();
            // Schrift
            $table->string('font_heading', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_style_presets');
    }
};
