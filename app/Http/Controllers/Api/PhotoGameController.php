<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventPhotoGame;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoGameAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class PhotoGameController extends Controller
{
    public function status(Request $request)
    {
        $guest = $request->user();
        $game  = EventPhotoGame::where('event_id', $guest->event_id)->first();

        if (!$game) {
            return response()->json(['status' => 'draft', 'assignment' => null]);
        }

        $assignment = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->with(['task:id,description', 'photo:id,url'])
            ->first();

        return response()->json([
            'status'     => $game->status,
            'assignment' => $assignment ? [
                'id'           => $assignment->id,
                'task'         => $assignment->task ? [
                    'id'          => $assignment->task->id,
                    'description' => $assignment->task->description,
                ] : null,
                'submitted_at' => $assignment->submitted_at,
                'photo_url'    => $assignment->photo?->url,
            ] : null,
        ]);
    }

    public function assign(Request $request)
    {
        $guest = $request->user();
        $game  = EventPhotoGame::where('event_id', $guest->event_id)->first();

        if (!$game || $game->status !== EventPhotoGame::STATUS_ACTIVE) {
            return response()->json(['message' => 'Das Spiel ist nicht aktiv.'], 422);
        }

        if (!$game->catalog_id) {
            return response()->json(['message' => 'Kein Aufgaben-Katalog ausgewählt.'], 422);
        }

        // Bereits ein Assignment vorhanden?
        $existing = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Du hast bereits eine Aufgabe erhalten.'], 409);
        }

        // Zufälligen aktiven Task aus dem gewählten Katalog
        $task = $game->catalog->tasks()->active()->inRandomOrder()->first();

        if (!$task) {
            return response()->json(['message' => 'Keine Aufgaben im Katalog vorhanden.'], 422);
        }

        $assignment = PhotoGameAssignment::create([
            'game_id'  => $game->id,
            'guest_id' => $guest->id,
            'task_id'  => $task->id,
        ]);

        return response()->json([
            'id'   => $assignment->id,
            'task' => [
                'id'          => $task->id,
                'description' => $task->description,
            ],
        ], 201);
    }

    public function submit(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,heic,heif', 'max:10240'],
        ]);

        $guest = $request->user();
        $game  = EventPhotoGame::where('event_id', $guest->event_id)->first();

        if (!$game || $game->status !== EventPhotoGame::STATUS_ACTIVE) {
            return response()->json(['message' => 'Das Spiel ist nicht aktiv.'], 422);
        }

        $assignment = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Du hast noch keine Aufgabe erhalten.'], 422);
        }

        if ($assignment->submitted_at) {
            return response()->json(['message' => 'Du hast bereits ein Foto eingereicht.'], 409);
        }

        // Foto hochladen (analog zu Api/PhotoController)
        $file = $request->file('photo');
        $mime = strtolower($file->getClientOriginalExtension());

        if (in_array($mime, ['heic', 'heif'])) {
            $manager   = new ImageManager(new Driver());
            $imageData = $manager->read($file->getRealPath())->toJpeg(90)->toString();
            $path      = 'photos/' . Str::uuid() . '.jpg';
            Storage::disk('s3')->put($path, $imageData, 'public');
        } else {
            $path = 'photos/' . Str::uuid() . '.' . $mime;
            Storage::disk('s3')->put($path, file_get_contents($file), 'public');
        }

        $url = Storage::disk('s3')->url($path);

        // Photo im photo_game Album speichern
        $album = PhotoAlbum::where('event_id', $guest->event_id)
            ->where('slug', 'photo_game')
            ->first();

        $photo = Photo::create([
            'event_id' => $guest->event_id,
            'album_id' => $album?->id,
            'guest_id' => $guest->id,
            'url'      => $url,
            'r2_key'   => $path,
        ]);

        $assignment->update([
            'photo_id'     => $photo->id,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'photo_url'    => $url,
            'submitted_at' => $assignment->submitted_at,
        ]);
    }
}
