<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drink-Logs + alte Event-Drinks löschen – Schema-Umbau erfordert sauberen Start
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('drink_logs')->truncate();
        DB::table('drinks')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::table('drinks', function (Blueprint $table) {
            $table->dropColumn(['name', 'points', 'size_ml']);
            $table->foreignId('drink_catalog_id')
                ->after('event_id')
                ->constrained('drink_catalog')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drinks', function (Blueprint $table) {
            $table->dropForeign(['drink_catalog_id']);
            $table->dropColumn('drink_catalog_id');
            $table->string('name')->after('event_id');
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedSmallInteger('size_ml')->nullable();
        });
    }
};
