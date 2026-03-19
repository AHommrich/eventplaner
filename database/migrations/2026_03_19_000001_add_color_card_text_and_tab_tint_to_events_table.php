<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('color_card_text', 7)->nullable()->after('color_card');
            $table->string('color_tab_tint', 7)->nullable()->after('color_card_text');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['color_card_text', 'color_tab_tint']);
        });
    }
};
