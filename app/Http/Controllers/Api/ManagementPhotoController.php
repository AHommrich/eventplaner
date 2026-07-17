<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementPhotoController extends Controller
{
    /** List every gallery and photo for the resolved event. */
    public function index(Request $request): JsonResponse
    {
        $event = $this->event($request);

        $albums = $event->photoAlbums()
            ->with(['photos' => fn ($query) => $query
                ->with(['guest', 'gameAssignment.task', 'gameAssignment.override'])
                ->latest()])
            ->get()
            ->map(fn ($album) => [
                'id' => $album->id,
                'slug' => $album->slug,
                'name' => $album->name,
                'sort_order' => $album->sort_order,
                'photos' => $album->photos->map(fn (Photo $photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url,
                    'guest_name' => $photo->guest
                        ? trim(($photo->guest->firstname ?? '').' '.($photo->guest->lastname ?? ''))
                        : null,
                    'uploaded_by' => $photo->uploaded_by,
                    'uploader_role' => $photo->uploader_role,
                    'description' => $photo->description,
                    'task_description' => $photo->gameAssignment?->override?->custom_text
                        ?? $photo->gameAssignment?->task?->description,
                    'created_at' => $photo->created_at->toIso8601String(),
                ]),
            ]);

        return response()->json(['albums' => $albums]);
    }

    /** Managers may remove a photo from any gallery of the resolved event. */
    public function destroy(Request $request, Photo $photo)
    {
        abort_if($photo->event_id !== $this->event($request)->id, 403);

        // PhotoObserver removes the object-storage blob for every delete path.
        $photo->delete();

        return response()->noContent();
    }

    /** Validate every target before deleting any, preventing partial cross-event writes. */
    public function destroyBatch(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $photos = Photo::whereIn('id', $data['ids'])->get();
        if ($photos->count() !== count($data['ids'])) {
            return response()->json(['message' => 'One or more photos were not found.'], 422);
        }

        $event = $this->event($request);
        abort_if($photos->contains(fn (Photo $photo) => $photo->event_id !== $event->id), 403);

        $photos->each->delete();

        return response()->noContent();
    }

    private function event(Request $request): Event
    {
        return $request->attributes->get('management_event');
    }
}
