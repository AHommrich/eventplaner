<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventPhotoGame;
use App\Models\EventTaskOverride;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoGameAssignment;
use App\Models\PhotoGameTaskCatalog;
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
            ->with(['task:id,description', 'override:id,custom_text', 'photo:id,url'])
            ->first();

        return response()->json([
            'status'     => $game->status,
            'assignment' => $assignment ? [
                'id'           => $assignment->id,
                'task'         => [
                    'id'          => $assignment->task_id ?? $assignment->override_id,
                    'description' => $this->resolveTaskDescription($assignment),
                ],
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

        // Bereits ein Assignment vorhanden?
        $existing = PhotoGameAssignment::where('game_id', $game->id)
            ->where('guest_id', $guest->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Du hast bereits eine Aufgabe erhalten.'], 409);
        }

        // Pool on-the-fly aufbauen
        $pool = $this->buildAssignPool($guest->event_id, $game->catalog_id);

        if ($pool->isEmpty()) {
            return response()->json(['message' => 'Keine Aufgaben verfügbar.'], 422);
        }

        $picked = $pool->random();

        $assignment = PhotoGameAssignment::create([
            'game_id'     => $game->id,
            'guest_id'    => $guest->id,
            'task_id'     => $picked['task_id'],
            'override_id' => $picked['override_id'],
        ]);

        return response()->json([
            'id'   => $assignment->id,
            'task' => [
                'id'          => $picked['task_id'] ?? $picked['override_id'],
                'description' => $picked['description'],
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

        // Altes Foto löschen (Re-Submission)
        if ($assignment->photo_id) {
            $oldPhoto = $assignment->photo;
            if ($oldPhoto) {
                Storage::disk('s3')->delete($oldPhoto->r2_key);
                $oldPhoto->delete();
            }
        }

        // Foto hochladen
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

    /** Baut den Pool für die assign()-Methode als Collection */
    private function buildAssignPool(int $eventId, ?int $typeCatalogId): \Illuminate\Support\Collection
    {
        // 1. Basis-Tasks
        $baseCatalog = PhotoGameTaskCatalog::base()->first();
        $pool = $baseCatalog
            ? $baseCatalog->tasks()->active()->get()->map(fn($t) => [
                'task_id'     => $t->id,
                'override_id' => null,
                'description' => $t->description,
            ])
            : collect();

        // 2. Event-Typ-Tasks
        if ($typeCatalogId) {
            $typeCatalog = PhotoGameTaskCatalog::find($typeCatalogId);
            if ($typeCatalog) {
                $typeItems = $typeCatalog->tasks()->active()->get()->map(fn($t) => [
                    'task_id'     => $t->id,
                    'override_id' => null,
                    'description' => $t->description,
                ]);
                $pool = $pool->concat($typeItems);
            }
        }

        // 3. Overrides anwenden
        $overrides = EventTaskOverride::where('event_id', $eventId)->get();

        foreach ($overrides as $ov) {
            if ($ov->action === 'hidden') {
                $pool = $pool->reject(fn($t) => $t['task_id'] === $ov->task_id);
            } elseif ($ov->action === 'modified') {
                $pool = $pool->map(fn($t) => $t['task_id'] === $ov->task_id
                    ? array_merge($t, ['description' => $ov->custom_text])
                    : $t);
            } elseif ($ov->action === 'added') {
                $pool->push([
                    'task_id'     => null,
                    'override_id' => $ov->id,
                    'description' => $ov->custom_text,
                ]);
            }
        }

        return $pool->values();
    }

    /** Löst die Aufgabenbeschreibung für ein Assignment auf */
    private function resolveTaskDescription(PhotoGameAssignment $assignment): string
    {
        if ($assignment->override_id && $assignment->override) {
            return $assignment->override->custom_text ?? '';
        }
        if ($assignment->task_id && $assignment->task) {
            // Prüfen ob ein 'modified'-Override existiert
            $mod = EventTaskOverride::where('event_id', $assignment->game->event_id ?? 0)
                ->where('task_id', $assignment->task_id)
                ->where('action', 'modified')
                ->first();
            return $mod?->custom_text ?? $assignment->task->description;
        }
        return '';
    }
}
