<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('drinks', function (Blueprint $table) {
            $table->unsignedSmallInteger('points')->default(1)->after('name');
            $table->unsignedSmallInteger('size_ml')->nullable()->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('drinks', function (Blueprint $table) {
            $table->dropColumn(['points', 'size_ml']);
        });
    }
};
