<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nur ausführen wenn noch kein Event existiert
        if (DB::table('events')->exists()) {
            return;
        }

        $admin = DB::table('users')->where('role', 'admin')->first();
        if (!$admin) {
            return;
        }

        $eventId = DB::table('events')->insertGetId([
            'user_id'    => $admin->id,
            'name'       => 'Hochzeit André & Tabea',
            'slug'       => 'hochzeit-andre-tabea',
            'date'       => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Alle bestehenden Datensätze ohne event_id dem Standard-Event zuordnen
        DB::table('guests')->whereNull('event_id')->update(['event_id' => $eventId]);
        DB::table('groups')->whereNull('event_id')->update(['event_id' => $eventId]);
        DB::table('photos')->whereNull('event_id')->update(['event_id' => $eventId]);
    }

    public function down(): void
    {
        // Nicht rückgängig machen — zu riskant für Produktionsdaten
    }
};
