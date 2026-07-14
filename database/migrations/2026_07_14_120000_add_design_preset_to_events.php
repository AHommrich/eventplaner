<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design preset — the "form language" layer the guest app renders on top of the
 * colour system (radius, surface, shadow, density, tab bar). Orthogonal to the
 * palette/roles: the couple picks colours AND, independently, a preset.
 * `classic` is the current look; `soft-luxury` is the frosted/rounded variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('design_preset')->default('classic')->after('font_heading');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('design_preset');
        });
    }
};
