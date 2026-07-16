<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Presets created before role_nav_bg existed have an intentional card
        // background but no navbar assignment. The navbar should follow that
        // surface instead of falling back to an unrelated palette colour.
        DB::table('event_style_presets')
            ->whereNull('role_nav_bg')
            ->update(['role_nav_bg' => DB::raw('role_card_bg')]);
    }

    public function down(): void
    {
        // The original null state cannot be distinguished from later edits.
    }
};
