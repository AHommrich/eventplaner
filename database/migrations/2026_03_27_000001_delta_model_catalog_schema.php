<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migration A: photo_game_task_catalogs um is_base + event_type erweitern;
// alle event-spezifischen Kataloge + ihre Tasks löschen;
// event_photo_games.catalog_id auf null setzen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_game_task_catalogs', function (Blueprint $table) {
            $table->boolean('is_base')->default(false)->after('is_active');
            $table->string('event_type')->nullable()->after('is_base');
        });

        // Allgemein-Katalog als Basis markieren
        DB::table('photo_game_task_catalogs')
            ->whereNull('event_id')
            ->where('name', 'Allgemein')
            ->update(['is_base' => true]);

        // event_photo_games: catalog_id auf null setzen bevor event-spez. Kataloge gelöscht werden
        DB::table('event_photo_games')->update(['catalog_id' => null]);

        // Event-spezifische Kataloge löschen (Tasks werden via cascade gelöscht)
        DB::table('photo_game_task_catalogs')
            ->whereNotNull('event_id')
            ->delete();
    }

    public function down(): void
    {
        Schema::table('photo_game_task_catalogs', function (Blueprint $table) {
            $table->dropColumn(['is_base', 'event_type']);
        });
    }
};
