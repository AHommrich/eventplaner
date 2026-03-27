<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration D: Alle alten Tasks löschen, globale Kataloge mit korrekten Daten neu befüllen.
// Katalogstruktur:
//   - Allgemein (is_base=true, event_type=null):  15 allgemeine Aufgaben
//   - Hochzeit  (is_base=false, event_type='hochzeit'): 9 hochzeitsspezifische Aufgaben
//   - Geburtstag(is_base=false, event_type='geburtstag'): 8 geburtstagsspez. Aufgaben
return new class extends Migration
{
    public function up(): void
    {
        // Alle bestehenden globalen Tasks entfernen
        $globalCatalogIds = DB::table('photo_game_task_catalogs')
            ->whereNull('event_id')
            ->pluck('id');
        DB::table('photo_game_tasks')->whereIn('catalog_id', $globalCatalogIds)->delete();

        // Bestehende globale Kataloge bereinigen (nur Allgemein, Hochzeits-Klassiker, Geburtstag behalten)
        DB::table('photo_game_task_catalogs')->whereNull('event_id')->delete();

        $now = now();

        // --- Allgemein (is_base = true) ---
        $allgemeinId = DB::table('photo_game_task_catalogs')->insertGetId([
            'event_id'   => null,
            'name'       => 'Allgemein',
            'is_active'  => true,
            'is_base'    => true,
            'event_type' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $allgemeinTasks = [
            'Jemanden beim Lachen',
            'Jemanden beim Tanzen',
            'Zwei Menschen im Gespräch',
            'Ein lustiges Gruppenbild',
            'Eine Umarmung',
            'Jemanden in ungewöhnlicher Pose',
            'Zwei Generationen zusammen',
            'Ein Getränk',
            'Etwas das auf keiner Party fehlen darf',
            'Jemanden beim Essen',
            'Das Buffet / die Snacks',
            'Die Deko im Überblick',
            'Zwei Menschen die sich lange nicht / noch nie gesehen haben',
            'Jemanden beim Spielen',
            'Jemanden der gerade nicht weiß dass er fotografiert wird',
        ];

        foreach ($allgemeinTasks as $i => $desc) {
            DB::table('photo_game_tasks')->insert([
                'catalog_id'  => $allgemeinId,
                'description' => $desc,
                'sort_order'  => $i,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // --- Hochzeit (is_base = false, event_type = 'hochzeit') ---
        $hochzeitId = DB::table('photo_game_task_catalogs')->insertGetId([
            'event_id'   => null,
            'name'       => 'Hochzeit',
            'is_active'  => true,
            'is_base'    => false,
            'event_type' => 'hochzeit',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $hochzeitTasks = [
            'Das Brautpaar beim ersten Tanz',
            'Selfie mit dem Tischnachbarn',
            'Die Torte',
            'Die Ringe',
            'Die ganze Hochzeitsgesellschaft (wenn möglich)',
            'Etwas Blaues auf der Hochzeit',
            'Zwei ältere Gäste beim Tanzen',
            'Ein Kuss',
            'Die Blumendeko',
        ];

        foreach ($hochzeitTasks as $i => $desc) {
            DB::table('photo_game_tasks')->insert([
                'catalog_id'  => $hochzeitId,
                'description' => $desc,
                'sort_order'  => $i,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // --- Geburtstag (is_base = false, event_type = 'geburtstag') ---
        $geburtstagId = DB::table('photo_game_task_catalogs')->insertGetId([
            'event_id'   => null,
            'name'       => 'Geburtstag',
            'is_active'  => true,
            'is_base'    => false,
            'event_type' => 'geburtstag',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $geburtstagTasks = [
            'Den Geburtstagskuchen',
            'Ein Foto mit dem Geburtstagskind',
            'Jemanden beim Singen',
            'Die schönste Geschenkverpackung',
            'Das bunteste Outfit',
            'Den ältesten und den jüngsten Gast zusammen',
            'Ein Gruppenphoto mit mindestens 5 Personen',
            'Die Geschenke',
        ];

        foreach ($geburtstagTasks as $i => $desc) {
            DB::table('photo_game_tasks')->insert([
                'catalog_id'  => $geburtstagId,
                'description' => $desc,
                'sort_order'  => $i,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Nicht rückgängig machen
    }
};
