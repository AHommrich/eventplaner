<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a dedicated `role_nav_bg` colour role (bottom tab bar background).
 *
 * Before this, the tab bar background was NOT a role — it was derived per
 * preset (classic used the screen background, soft-luxury used the card
 * colour). That made the bar inconsistent across a preset swap and gave the
 * couple no control over its contrast. `role_nav_bg` makes it an explicit
 * role like every other surface; the tab-tint role stays the foreground
 * (icons/labels). Default `secondary` keeps classic identical to before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('role_nav_bg', 20)->nullable()->after('role_fab_icon');
        });

        Schema::table('event_style_presets', function (Blueprint $table) {
            $table->string('role_nav_bg', 20)->nullable()->after('role_fab_icon');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('role_nav_bg');
        });

        Schema::table('event_style_presets', function (Blueprint $table) {
            $table->dropColumn('role_nav_bg');
        });
    }
};
