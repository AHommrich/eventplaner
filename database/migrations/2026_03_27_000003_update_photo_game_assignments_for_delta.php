<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration C: task_id nullable machen (für 'added'-Tasks ohne globale task_id)
// + override_id FK hinzufügen
return new class extends Migration
{
    public function up(): void
    {
        // Bestehende Assignments löschen (task_id FK muss nullable werden)
        // Da es sich um Testdaten handelt und ein sauberer Neustart gewünscht ist
        \Illuminate\Support\Facades\DB::table('photo_game_assignments')->delete();

        Schema::table('photo_game_assignments', function (Blueprint $table) {
            // task_id nullable machen (MariaDB: raw ALTER TABLE wegen UNSIGNED)
            $table->foreignId('task_id')->nullable()->change();
            $table->foreignId('override_id')
                ->nullable()
                ->after('task_id')
                ->constrained('event_task_overrides')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photo_game_assignments', function (Blueprint $table) {
            $table->dropForeign(['override_id']);
            $table->dropColumn('override_id');
            $table->foreignId('task_id')->nullable(false)->change();
        });
    }
};
