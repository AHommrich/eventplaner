<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventPhotoGame;
use App\Models\EventTaskOverride;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoGameAssignment;
use App\Services\PhotoGameTaskPool;
use App\Services\PhotoSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo-game API for the React Native app.
 *
 *  - GET  /status  → game status + current task of the guest (with submitted photo, if any)
 *  - POST /assign  → assign a new task; pool from base catalog + type catalog + overrides
 *  - POST /submit  → submit a photo for the open task (re-submission allowed, old photo deleted)
 *
 * Pool construction is delegated to {@see PhotoGameTaskPool} (delta-model logic there).
 * Task descriptions support DE/EN via `Accept-Language`; overrides are
 * intentionally DE only (entered by the organizer, no translation obligation).
 */
class PhotoGameController extends Controller
{
    public function __construct(private readonly PhotoGameTaskPool $pool) {}

    public function status(Request $request)
    {
        $guest = $request->user();
        $lang = $request->getPreferredLanguage(['de', 'en']);
        $game = EventPhotoGame::where('event_id', $guest->event_id)->first();

        if (! $game) {
            return response()->json(['status' => 'draft', 'assignment' => null]);
        }

        $assignment = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->with(['task:id,description,description_en,translation_key', 'override:id,custom_text', 'photo:id,url'])
            ->first();

        return response()->json([
            'status' => $game->status,
            'assignment' => $assignment ? [
                'id' => $assignment->id,
                'task' => [
                    'id' => $assignment->task_id ?? $assignment->override_id,
                    'description' => $this->resolveTaskDescription($assignment, $lang),
                    'translation_key' => $assignment->override ? null : $assignment->task?->translation_key,
                ],
                'submitted_at' => $assignment->submitted_at,
                'photo_url' => $assignment->photo?->url,
            ] : null,
        ]);
    }

    public function assign(Request $request)
    {
        $guest = $request->user();
        $game = EventPhotoGame::where('event_id', $guest->event_id)->first();

        if (! $game || $game->status !== EventPhotoGame::STATUS_ACTIVE) {
            return response()->json(['message' => 'Das Spiel ist nicht aktiv.'], 422);
        }

        // assignment already exists?
        $existing = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Du hast bereits eine Aufgabe erhalten.'], 409);
        }

        $lang = $request->getPreferredLanguage(['de', 'en']);

        // build pool on-the-fly (base + type + overrides, see {@see PhotoGameTaskPool})
        $pool = $this->pool->build($guest->event_id, $game->catalog_id);

        if ($pool->isEmpty()) {
            return response()->json(['message' => 'Keine Aufgaben verfügbar.'], 422);
        }

        $picked = $pool->random();

        $assignment = PhotoGameAssignment::create([
            'game_id' => $game->id,
            'guest_id' => $guest->id,
            'task_id' => $picked['task_id'],
            'override_id' => $picked['override_id'],
        ]);

        $description = ($lang === 'en' && ! empty($picked['description_en']))
            ? $picked['description_en']
            : $picked['description'];

        return response()->json([
            'id' => $assignment->id,
            'task' => [
                'id' => $picked['task_id'] ?? $picked['override_id'],
                'description' => $description,
                'translation_key' => $picked['translation_key'] ?? null,
            ],
        ], 201);
    }

    public function submit(Request $request, PhotoSanitizer $sanitizer)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,heic,heif', 'max:10240'],
        ]);

        $guest = $request->user();
        $game = EventPhotoGame::where('event_id', $guest->event_id)->first();

        if (! $game || $game->status !== EventPhotoGame::STATUS_ACTIVE) {
            return response()->json(['message' => 'Das Spiel ist nicht aktiv.'], 422);
        }

        $assignment = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->first();

        if (! $assignment) {
            return response()->json(['message' => 'Du hast noch keine Aufgabe erhalten.'], 422);
        }

        // delete old photo (re-submission)
        if ($assignment->photo_id) {
            $oldPhoto = $assignment->photo;
            if ($oldPhoto) {
                Storage::disk('s3')->delete($oldPhoto->r2_key);
                $oldPhoto->delete();
            }
        }

        // upload photo: re-encode to JPEG without EXIF via PhotoSanitizer.
        $file = $request->file('photo');
        $imageData = $sanitizer->toJpegWithoutExif($file->getRealPath());
        $path = 'photos/'.Str::uuid().'.jpg';
        Storage::disk('s3')->put($path, $imageData, 'public');

        $url = Storage::disk('s3')->url($path);

        $album = PhotoAlbum::where('event_id', $guest->event_id)
            ->where('slug', 'photo_game')
            ->first();

        $photo = Photo::create([
            'event_id' => $guest->event_id,
            'album_id' => $album?->id,
            'guest_id' => $guest->id,
            'url' => $url,
            'r2_key' => $path,
        ]);

        $assignment->update([
            'photo_id' => $photo->id,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'photo_url' => $url,
            'submitted_at' => $assignment->submitted_at,
        ]);
    }

    /** Resolves the task description for an assignment */
    private function resolveTaskDescription(PhotoGameAssignment $assignment, string $lang = 'de'): string
    {
        if ($assignment->override_id && $assignment->override) {
            // override texts are always German only (entered by the organizer)
            return $assignment->override->custom_text ?? '';
        }
        if ($assignment->task_id && $assignment->task) {
            // check whether a 'modified' override exists
            $mod = EventTaskOverride::where('event_id', $assignment->game->event_id ?? 0)
                ->where('task_id', $assignment->task_id)
                ->where('action', 'modified')
                ->first();
            if ($mod) {
                return $mod->custom_text ?? '';
            }
            if ($lang === 'en' && $assignment->task->description_en) {
                return $assignment->task->description_en;
            }

            return $assignment->task->description;
        }

        return '';
    }
}
