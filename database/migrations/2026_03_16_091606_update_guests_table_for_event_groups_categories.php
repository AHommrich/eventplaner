<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // event_id hinzufügen
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete()->after('id');

            // badge_id → category_id (Tabelle wurde in categories umbenannt)
            $table->dropConstrainedForeignId('badge_id');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('event_id');

            // family_id → group_id (Tabelle wurde in groups umbenannt)
            $table->dropConstrainedForeignId('family_id');
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropConstrainedForeignId('category_id');
            $table->foreignId('badge_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->dropConstrainedForeignId('group_id');
            $table->foreignId('family_id')->nullable()->constrained('groups')->nullOnDelete();
        });
    }
};
