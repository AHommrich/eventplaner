<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Services\PhotoSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Organizer management of the photos of an event (Inertia, NOT the guest API).
 *
 *  - `index()`                     → all albums with photos + projector URL
 *  - `store()`                     → upload (HEIC→JPEG via Imagick); lands in the right album based on `album_slug`
 *  - `destroy()` / `destroyBatch()` → delete photo record + object-storage blob
 *  - `updateProjectorAlbum()` / `updateProjectorNameMode()` → display config of the projector slideshow
 *  - `regenerateProjectorToken()`  → creates a new 32-char token (old projector link becomes invalid)
 */
class PhotoController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        if (! $event) {
            return Inertia::render('Photos/Index', [
                'albums' => [],
                'projectorUrl' => null,
                'projectorAlbumId' => null,
            ]);
        }

        $ownerId = $event->user_id;

        $albums = $event->photoAlbums()
            ->with(['photos' => function ($q) {
                $q->with('guest')->latest();
            }])
            ->get()
            ->map(fn ($album) => [
                'id' => $album->id,
                'slug' => $album->slug,
                'name' => $album->name,
                'sort_order' => $album->sort_order,
                'photos' => $album->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url,
                    'guest_name' => $photo->guest
                        ? trim(($photo->guest->firstname ?? '').' '.($photo->guest->lastname ?? ''))
                        : ($photo->uploaded_by ?? null),
                    'organizer_role' => $photo->guest === null && $photo->uploaded_by !== null
                        ? ($photo->uploader_user_id !== null && $photo->uploader_user_id !== $ownerId
                            ? 'co_organizer'
                            : 'owner')
                        : null,
                    'description' => $photo->description,
                    'created_at' => $photo->created_at->format('d.m.Y H:i'),
                ]),
            ]);

        $projectorUrl = $event->projector_token
            ? route('projector.show', $event->projector_token)
            : null;

        return Inertia::render('Photos/Index', [
            'albums' => $albums,
            'projectorUrl' => $projectorUrl,
            'projectorAlbumId' => $event->projector_album_id,
            'projectorNameMode' => $event->projector_name_mode ?? 'first',
        ]);
    }

    public function store(Request $request, PhotoSanitizer $sanitizer)
    {
        $event = $this->activeEvent();

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,jpg,png,heic,heif', 'max:10240'],
            'album_id' => ['nullable', 'integer', 'exists:photo_albums,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('photo');

        // Re-encode to JPEG without EXIF on every upload path — see PhotoSanitizer.
        $contents = $sanitizer->toJpegWithoutExif($file->getPathname());

        $path = 'photos/'.Str::uuid().'.jpg';
        Storage::disk('s3')->put($path, $contents, 'public');

        // default: party album for admin uploads
        $albumId = $request->input('album_id');
        if (! $albumId && $event) {
            $partyAlbum = $event->photoAlbums()->where('slug', PhotoAlbum::PRESENTATION)->first();
            $albumId = $partyAlbum?->id;
        }

        Photo::create([
            'event_id' => $event?->id,
            'album_id' => $albumId,
            'guest_id' => null,
            'uploaded_by' => $request->user()->name,
            'uploader_user_id' => $request->user()->id,
            'url' => Storage::disk('s3')->url($path),
            'r2_key' => $path,
            'description' => $request->input('description') ?: null,
        ]);

        return back();
    }

    public function destroy(Photo $photo)
    {
        $key = $photo->r2_key ?? ltrim(parse_url($photo->url, PHP_URL_PATH), '/');
        Storage::disk('s3')->delete($key);
        $photo->delete();

        return back();
    }

    public function destroyBatch(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:photos,id'],
        ]);

        $event = $this->activeEvent();
        $photos = Photo::whereIn('id', $request->input('ids'))
            ->where('event_id', $event?->id)
            ->get();

        foreach ($photos as $photo) {
            $key = $photo->r2_key ?? ltrim(parse_url($photo->url, PHP_URL_PATH), '/');
            Storage::disk('s3')->delete($key);
            $photo->delete();
        }

        return back();
    }

    public function updateProjectorAlbum(Request $request)
    {
        $request->validate([
            'album_id' => ['required', 'integer', 'exists:photo_albums,id'],
        ]);

        $event = $this->activeEvent();
        $event?->update(['projector_album_id' => $request->input('album_id')]);

        return back();
    }

    public function updateProjectorNameMode(Request $request)
    {
        $request->validate([
            'name_mode' => ['required', 'in:full,first,none'],
        ]);

        $event = $this->activeEvent();
        $event?->update(['projector_name_mode' => $request->input('name_mode')]);

        return back();
    }

    public function regenerateProjectorToken()
    {
        $event = $this->activeEvent();
        $event?->update(['projector_token' => Str::random(32)]);

        return back();
    }
}
