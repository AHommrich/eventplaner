<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('color_home_shadow', 7)->default('#000000')->after('color_home_text');
            $table->unsignedTinyInteger('home_shadow_opacity')->default(50)->after('color_home_shadow');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['color_home_shadow', 'home_shadow_opacity']);
        });
    }
};
