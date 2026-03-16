<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // event_id hinzufügen (guard: läuft idempotent)
        if (!Schema::hasColumn('guests', 'event_id')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete()->after('id');
            });
        }

        // badge_id → category_id via raw SQL (zuverlässiger als renameColumn bei FK-Spalten)
        if (Schema::hasColumn('guests', 'badge_id')) {
            DB::statement('ALTER TABLE guests DROP FOREIGN KEY guests_badge_id_foreign');
            DB::statement('ALTER TABLE guests CHANGE badge_id category_id BIGINT UNSIGNED NULL');
        }

        // family_id → group_id via raw SQL
        if (Schema::hasColumn('guests', 'family_id')) {
            DB::statement('ALTER TABLE guests DROP FOREIGN KEY guests_family_id_foreign');
            DB::statement('ALTER TABLE guests CHANGE family_id group_id BIGINT UNSIGNED NULL');
        }

        // FK-Constraints anlegen (nur wenn noch nicht vorhanden)
        $existingFKs = collect(DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'guests'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        "))->pluck('CONSTRAINT_NAME');

        Schema::table('guests', function (Blueprint $table) use ($existingFKs) {
            if (!$existingFKs->contains('guests_category_id_foreign')) {
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            }
            if (!$existingFKs->contains('guests_group_id_foreign')) {
                $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['group_id']);
            $table->dropConstrainedForeignId('event_id');
        });

        DB::statement('ALTER TABLE guests CHANGE category_id badge_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE guests CHANGE group_id family_id BIGINT UNSIGNED NULL');

        Schema::table('guests', function (Blueprint $table) {
            $table->foreign('badge_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('family_id')->references('id')->on('groups')->nullOnDelete();
        });
    }
};
