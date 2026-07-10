<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestContentHide;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoHide;
use App\Services\PhotoSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo upload and list for guests (app gallery album).
 *
 *  - POST /api/photos  → multipart 'photo' field; HEIC/HEIF are converted
 *                        server-side via Imagick to JPEG and stored on Hetzner Object Storage (S3 API)
 *  - GET  /api/photos  → list of all app-gallery photos of the event (with uploader's first name)
 *
 * Album routing: uploads automatically land in the default album with slug
 * {@see \App\Models\PhotoAlbum::APP_GALLERY}. Other album slugs (presentation,
 * photo game) are reserved for the organizer or the photo-game flow.
 */
class PhotoController extends Controller
{
    public function store(Request $request, PhotoSanitizer $sanitizer)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,heic,heif', 'max:10240'],
        ]);

        $guest = $request->user();
        $file = $request->file('photo');

        // Re-encode every upload to a JPEG without EXIF — GDPR / privacy policy
        // promises that GPS coordinates and device metadata never leave the app server.
        $imageData = $sanitizer->toJpegWithoutExif($file->getRealPath());
        $path = 'photos/'.Str::uuid().'.jpg';
        Storage::disk('s3')->put($path, $imageData, 'public');

        $url = Storage::disk('s3')->url($path);

        // guests always upload to app_gallery
        $album = PhotoAlbum::where('event_id', $guest->event_id)
            ->where('slug', PhotoAlbum::APP_GALLERY)
            ->first();

        $photo = Photo::create([
            'event_id' => $guest->event_id,
            'album_id' => $album?->id,
            'guest_id' => $guest->id,
            'url' => $url,
            'r2_key' => $path,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
            'guest_name' => $guest->firstname,
            'created_at' => $photo->created_at,
        ], 201);
    }

    public function index(Request $request)
    {
        $guest = $request->user();

        // only app_gallery for guests
        $album = PhotoAlbum::where('event_id', $guest->event_id)
            ->where('slug', PhotoAlbum::APP_GALLERY)
            ->first();

        // moderation filters — server-side, App-Store-Guideline-1.2
        $hiddenGuestIds = GuestContentHide::where('viewer_guest_id', $guest->id)
            ->pluck('hidden_guest_id')
            ->all();
        $hiddenPhotoIds = PhotoHide::where('viewer_guest_id', $guest->id)
            ->pluck('photo_id')
            ->all();

        $query = Photo::with('guest')->where('event_id', $guest->event_id);
        if ($album) {
            $query->where('album_id', $album->id);
        }
        // hide-content filter: owner uploads (guest_id null) stay visible
        if (! empty($hiddenGuestIds)) {
            $query->where(function ($q) use ($hiddenGuestIds) {
                $q->whereNull('guest_id')
                    ->orWhereNotIn('guest_id', $hiddenGuestIds);
            });
        }
        if (! empty($hiddenPhotoIds)) {
            $query->whereNotIn('id', $hiddenPhotoIds);
        }

        $photos = $query->latest()->get()->map(fn ($photo) => [
            'id' => $photo->id,
            'url' => $photo->url,
            'guest_id' => $photo->guest_id,
            'guest_name' => $photo->guest?->firstname ?? $photo->uploaded_by ?? 'Admin',
            'created_at' => $photo->created_at,
        ]);

        return response()->json(['data' => $photos]);
    }

    public function destroy(Request $request, Photo $photo)
    {
        $guest = $request->user();

        abort_if($photo->event_id !== $guest->event_id, 404);

        $album = $photo->album;
        abort_if(! $album || $album->slug !== PhotoAlbum::APP_GALLERY, 404);

        abort_if($photo->guest_id !== $guest->id, 403);

        $photo->delete();

        return response()->json(null, 204);
    }
}
