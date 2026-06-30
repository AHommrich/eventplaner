<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

/**
 * Foto-Upload und -Liste für Gäste (App-Galerie-Album).
 *
 *  - POST /api/photos  → multipart 'photo'-Field; HEIC/HEIF werden serverseitig
 *                        via Imagick zu JPEG konvertiert, dann auf R2 (S3-API) abgelegt
 *  - GET  /api/photos  → Liste aller App-Galerie-Fotos des Events (mit Uploader-Vorname)
 *
 * Album-Routing: Uploads landen automatisch im Standard-Album mit Slug
 * {@see \App\Models\PhotoAlbum::APP_GALLERY}. Andere Album-Slugs (Präsentation,
 * Fotospiel) sind dem Veranstalter bzw. Fotospiel-Flow vorbehalten.
 */
class PhotoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,heic,heif', 'max:10240'],
        ]);

        $guest = $request->user();
        $file = $request->file('photo');
        $mime = strtolower($file->getClientOriginalExtension());

        if (in_array($mime, ['heic', 'heif'])) {
            $manager = new ImageManager(new Driver);
            $imageData = $manager->read($file->getRealPath())->toJpeg(90)->toString();
            $path = 'photos/'.Str::uuid().'.jpg';
            Storage::disk('s3')->put($path, $imageData, 'public');
        } else {
            $path = 'photos/'.Str::uuid().'.'.$mime;
            Storage::disk('s3')->put($path, file_get_contents($file), 'public');
        }

        $url = Storage::disk('s3')->url($path);

        // Gäste laden immer in app_gallery hoch
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

        // Nur app_gallery für Gäste
        $album = PhotoAlbum::where('event_id', $guest->event_id)
            ->where('slug', PhotoAlbum::APP_GALLERY)
            ->first();

        $query = Photo::with('guest')->where('event_id', $guest->event_id);
        if ($album) {
            $query->where('album_id', $album->id);
        }

        $photos = $query->latest()->get()->map(fn ($photo) => [
            'id' => $photo->id,
            'url' => $photo->url,
            'guest_name' => $photo->guest?->firstname ?? $photo->uploaded_by ?? 'Admin',
            'created_at' => $photo->created_at,
        ]);

        return response()->json(['data' => $photos]);
    }
}
