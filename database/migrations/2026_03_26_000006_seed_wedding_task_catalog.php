<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $catalogId = DB::table('photo_game_task_catalogs')->insertGetId([
            'event_id'   => null,
            'name'       => 'Hochzeits-Klassiker',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tasks = [
            'Das Brautpaar beim ersten Tanz',
            'Ein Selfie mit deinem Tischnachbarn',
            'Das schönste Blumenarrangement',
            'Jemanden beim Lachen erwischen',
            'Die Torte von oben',
            'Ein Kind auf der Tanzfläche',
            'Die Ringe in Nahaufnahme',
            'Die Braut beim Ankleiden oder kurz davor',
            'Ein herzliches Umarmungsfoto',
            'Die ganze Hochzeitsgesellschaft',
            'Etwas Blaues auf der Hochzeit',
            'Ein romantischer Sonnenuntergang',
            'Das Buffet in voller Pracht',
            'Zwei ältere Gäste beim Tanzen',
            'Ein spontaner Kuss',
            'Die leere Tanzfläche vor dem ersten Tanz',
            'Jemanden beim Weinen vor Freude',
            'Die Eingangsflower-Deko',
            'Ein Detail am Brautkleid',
            'Das Brautpaar von hinten, Hand in Hand',
        ];

        foreach ($tasks as $i => $description) {
            DB::table('photo_game_tasks')->insert([
                'catalog_id'  => $catalogId,
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
