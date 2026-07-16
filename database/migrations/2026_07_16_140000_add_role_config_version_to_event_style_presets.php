<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_style_presets', function (Blueprint $table) {
            $table->unsignedTinyInteger('role_config_version')->default(1)->after('design_preset');
        });
    }

    public function down(): void
    {
        Schema::table('event_style_presets', function (Blueprint $table) {
            $table->dropColumn('role_config_version');
        });
    }
};
