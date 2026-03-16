<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // event_id hinzufügen
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete()->after('id');

            // badge_id → category_id: Spalte umbenennen (Daten bleiben erhalten!)
            // Die FK-Referenz zeigt jetzt auf 'categories' (vorher 'badges', in Migration 091604 umbenannt)
            $table->dropForeign(['badge_id']);
            $table->renameColumn('badge_id', 'category_id');

            // family_id → group_id: Spalte umbenennen (Daten bleiben erhalten!)
            // Die FK-Referenz zeigt jetzt auf 'groups' (vorher 'families', in Migration 091605 umbenannt)
            $table->dropForeign(['family_id']);
            $table->renameColumn('family_id', 'group_id');
        });

        // FK-Constraints nach dem Rename neu anlegen
        Schema::table('guests', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['group_id']);
            $table->dropConstrainedForeignId('event_id');
            $table->renameColumn('category_id', 'badge_id');
            $table->renameColumn('group_id', 'family_id');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->foreign('badge_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('family_id')->references('id')->on('groups')->nullOnDelete();
        });
    }
};
