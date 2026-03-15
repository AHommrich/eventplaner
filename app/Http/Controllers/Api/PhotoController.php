<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

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
            $manager = new ImageManager(new Driver());
            $imageData = $manager->read($file->getRealPath())->toJpeg(90)->toString();
            $path = 'photos/' . Str::uuid() . '.jpg';
            Storage::disk('s3')->put($path, $imageData, 'public');
        } else {
            $path = 'photos/' . Str::uuid() . '.' . $mime;
            Storage::disk('s3')->put($path, file_get_contents($file), 'public');
        }

        $url = Storage::disk('s3')->url($path);

        $photo = Photo::create([
            'guest_id' => $guest->id,
            'url'      => $url,
        ]);

        return response()->json([
            'id'         => $photo->id,
            'url'        => $photo->url,
            'guest_name' => $guest->firstname,
            'created_at' => $photo->created_at,
        ], 201);
    }

    public function index(Request $request)
    {
        $photos = Photo::with('guest')
            ->latest()
            ->get()
            ->map(fn($photo) => [
                'id'         => $photo->id,
                'url'        => $photo->url,
                'guest_name' => $photo->guest?->firstname ?? $photo->uploaded_by ?? 'Admin',
                'created_at' => $photo->created_at,
            ]);

        return response()->json(['data' => $photos]);
    }
}
