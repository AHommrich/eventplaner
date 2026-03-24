<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Slug + Name umbenennen
        DB::table('photo_albums')
            ->where('slug', 'party')
            ->update([
                'slug'       => 'presentation',
                'name'       => 'Präsentation',
                'sort_order' => 0,
                'updated_at' => now(),
            ]);

        DB::table('photo_albums')
            ->where('slug', 'app_gallery')
            ->update(['sort_order' => 1, 'updated_at' => now()]);

        // Projektor-Default auf Präsentation umstellen (war app_gallery)
        $presentationIds = DB::table('photo_albums')
            ->where('slug', 'presentation')
            ->pluck('id', 'event_id');

        foreach ($presentationIds as $eventId => $albumId) {
            DB::table('events')
                ->where('id', $eventId)
                ->update([
                    'projector_album_id' => $albumId,
                    'updated_at'         => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('photo_albums')
            ->where('slug', 'presentation')
            ->update([
                'slug'       => 'party',
                'name'       => 'Party',
                'updated_at' => now(),
            ]);
    }
};
