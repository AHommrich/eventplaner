<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- Geburtstag ---
        $birthdayCatalogId = DB::table('photo_game_task_catalogs')->insertGetId([
            'event_id'   => null,
            'name'       => 'Geburtstag',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $birthdayTasks = [
            'Den Geburtstagskuchen mit Kerzen',
            'Ein Selfie mit dem Geburtstagskind',
            'Das Ausblasen der Kerzen',
            'Jemanden beim Singen von "Happy Birthday" erwischen',
            'Die schönste Geschenkverpackung',
            'Einen lustigen Hut oder eine Partymütze',
            'Das bunteste Outfit auf der Party',
            'Jemanden beim Lachen erwischen',
            'Das Buffet oder die Snacks in ihrer vollen Pracht',
            'Eine spontane Umarmung',
            'Den ältesten und den jüngsten Gast zusammen',
            'Ein Gruppenphoto mit mindestens 5 Personen',
            'Jemanden beim Tanzen erwischen',
            'Die Deko im Detail',
            'Ein Getränk oder Cocktail von oben',
            'Die Geburtstagskarte mit dem lustigsten Inhalt',
            'Jemanden beim Spielen oder Lachen',
            'Das Geschenk das am meisten überrascht hat',
            'Zwei Menschen die sich lange nicht gesehen haben',
            'Den Abschluss-Moment: letztes Stück Kuchen oder letzte Kerze',
        ];

        foreach ($birthdayTasks as $i => $description) {
            DB::table('photo_game_tasks')->insert([
                'catalog_id'  => $birthdayCatalogId,
                'description' => $description,
                'sort_order'  => $i,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // --- Allgemein / Party ---
        $genericCatalogId = DB::table('photo_game_task_catalogs')->insertGetId([
            'event_id'   => null,
            'name'       => 'Allgemein',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $genericTasks = [
            'Ein lustiges Gruppenbild',
            'Jemanden beim Tanzen erwischen',
            'Das Detail, das sonst niemand beachtet',
            'Der vollste Teller auf dem Tisch',
            'Jemanden mit geschlossenen Augen',
            'Das schönste Licht im Raum',
            'Zwei Menschen im intensiven Gespräch',
            'Ein spontanes Lachen',
            'Die ungewöhnlichste Pose des Abends',
            'Jemanden von hinten',
            'Etwas das auf keiner Party fehlen darf',
            'Die aufgeräumteste oder unaufgeräumteste Ecke',
            'Ein Spiegelselfie mit mehreren Personen',
            'Der Blick aus dem Fenster',
            'Hände beim Anstoßen',
            'Jemanden beim Essen mit Genuss',
            'Das erste und letzte Getränk des Abends nebeneinander',
            'Ein Kind und ein Erwachsener machen dasselbe',
            'Jemanden mit dem besten Tanzschritt',
            'Das Ende des Abends — leere Gläser, volle Herzen',
        ];

        foreach ($genericTasks as $i => $description) {
            DB::table('photo_game_tasks')->insert([
                'catalog_id'  => $genericCatalogId,
                'description' => $description,
                'sort_order'  => $i,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Nicht rückgängig machen
    }
};
