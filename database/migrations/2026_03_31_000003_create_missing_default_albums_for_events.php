<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $events = DB::table('events')->pluck('id');

        $defaults = [
            ['slug' => 'app_gallery',  'name' => 'App Galerie',  'sort_order' => 1],
            ['slug' => 'presentation', 'name' => 'Präsentation', 'sort_order' => 2],
            ['slug' => 'photo_game',   'name' => 'Fotospiel',    'sort_order' => 3],
        ];

        $now = now();

        foreach ($events as $eventId) {
            foreach ($defaults as $album) {
                $exists = DB::table('photo_albums')
                    ->where('event_id', $eventId)
                    ->where('slug', $album['slug'])
                    ->exists();

                if (! $exists) {
                    DB::table('photo_albums')->insert([
                        'event_id' => $eventId,
                        'slug' => $album['slug'],
                        'name' => $album['name'],
                        'sort_order' => $album['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Nicht rückgängig machen — Alben sind sicher zu behalten
    }
};
