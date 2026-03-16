<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // date → datetime (MariaDB-safe)
        DB::statement("ALTER TABLE events MODIFY COLUMN `date` DATETIME NULL DEFAULT NULL");

        Schema::table('events', function (Blueprint $table) {
            $table->string('cover_image_url')->nullable()->after('date');
            $table->string('cover_image_r2_key')->nullable()->after('cover_image_url');
            $table->string('venue_name')->nullable()->after('cover_image_r2_key');
            $table->string('venue_address')->nullable()->after('venue_name');
            $table->text('dresscode')->nullable()->after('venue_address');
            $table->text('schedule')->nullable()->after('dresscode');
            $table->string('color_primary', 7)->nullable()->after('schedule');
            $table->string('color_secondary', 7)->nullable()->after('color_primary');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'cover_image_url', 'cover_image_r2_key',
                'venue_name', 'venue_address',
                'dresscode', 'schedule',
                'color_primary', 'color_secondary',
            ]);
        });
        DB::statement("ALTER TABLE events MODIFY COLUMN `date` DATE NULL DEFAULT NULL");
    }
};
