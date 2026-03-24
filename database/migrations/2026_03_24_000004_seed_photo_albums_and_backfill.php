<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $events = DB::table('events')->get();

        foreach ($events as $event) {
            $albums = [
                ['slug' => 'presentation',  'name' => 'Präsentation', 'sort_order' => 0],
                ['slug' => 'app_gallery',   'name' => 'App Galerie',  'sort_order' => 1],
                ['slug' => 'photo_game',    'name' => 'Fotospiel',    'sort_order' => 2],
            ];

            $appGalleryId    = null;
            $presentationId  = null;

            foreach ($albums as $album) {
                $id = DB::table('photo_albums')->insertGetId([
                    'event_id'   => $event->id,
                    'slug'       => $album['slug'],
                    'name'       => $album['name'],
                    'sort_order' => $album['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($album['slug'] === 'app_gallery')  $appGalleryId   = $id;
                if ($album['slug'] === 'presentation') $presentationId = $id;
            }

            // Bestehende Fotos → app_gallery
            if ($appGalleryId) {
                DB::table('photos')
                    ->where('event_id', $event->id)
                    ->whereNull('album_id')
                    ->update(['album_id' => $appGalleryId]);
            }

            // projector_token generieren + projector_album auf Präsentation setzen
            DB::table('events')
                ->where('id', $event->id)
                ->update([
                    'projector_token'    => Str::random(32),
                    'projector_album_id' => $presentationId,
                    'updated_at'         => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Nicht rückgängig machen
    }
};
