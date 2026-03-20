<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('venue_street')->nullable()->after('venue_address');
            $table->string('venue_house_number', 20)->nullable()->after('venue_street');
            $table->string('venue_postal_code', 20)->nullable()->after('venue_house_number');
            $table->string('venue_city')->nullable()->after('venue_postal_code');
            $table->string('venue_state')->nullable()->after('venue_city');
            $table->string('venue_country', 100)->nullable()->default('Deutschland')->after('venue_state');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'venue_street', 'venue_house_number', 'venue_postal_code',
                'venue_city', 'venue_state', 'venue_country',
            ]);
        });
    }
};
