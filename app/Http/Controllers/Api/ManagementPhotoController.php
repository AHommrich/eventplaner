<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Services\PhotoSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManagementPhotoController extends Controller
{
    /**
     * Album slugs an organizer may upload into via the generic management flow.
     * `photo_game` is excluded on purpose: a game photo without a
     * PhotoGameAssignment would be an orphan, so it is view/delete-only here.
     */
    private const UPLOADABLE_SLUGS = [PhotoAlbum::APP_GALLERY, PhotoAlbum::PRESENTATION];

    /**
     * Upload a photo into one of the resolved event's uploadable albums.
     *
     * The target album must belong to the resolved event and be app_gallery or
     * presentation. The image is re-encoded to JPEG without EXIF (privacy), and
     * the object is removed again if the DB write fails so storage never leaks
     * an orphan blob.
     */
    public function store(Request $request, PhotoSanitizer $sanitizer): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,heic,heif', 'max:10240'],
            'album_id' => ['required', 'integer'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $event = $this->event($request);

        $album = PhotoAlbum::where('id', $data['album_id'])
            ->where('event_id', $event->id)
            ->first();
        abort_if($album === null, 403);
        abort_unless(in_array($album->slug, self::UPLOADABLE_SLUGS, true), 422);

        $imageData = $sanitizer->toJpegWithoutExif($request->file('photo')->getRealPath());
        $path = 'photos/'.Str::uuid().'.jpg';
        Storage::disk('s3')->put($path, $imageData, 'public');

        try {
            $photo = Photo::create([
                'event_id' => $event->id,
                'album_id' => $album->id,
                'guest_id' => null,
                'uploaded_by' => $request->user()->name,
                'uploader_user_id' => $request->user()->id,
                'uploader_role' => $request->user()->roleOn($event),
                'url' => Storage::disk('s3')->url($path),
                'r2_key' => $path,
                'description' => $data['description'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Never leave an orphan object behind a failed DB write.
            Storage::disk('s3')->delete($path);
            throw $e;
        }

        return response()->json([
            'id' => $photo->id,
            'album_id' => $photo->album_id,
            'url' => $photo->url,
            'description' => $photo->description,
            'uploader_role' => $photo->uploader_role,
            'created_at' => $photo->created_at->toIso8601String(),
        ], 201);
    }

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
