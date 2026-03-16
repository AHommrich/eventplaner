<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PhotoController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        $photos = $event
            ? $event->photos()->with('guest')->latest()->get()
                ->map(fn($photo) => [
                    'id'         => $photo->id,
                    'url'        => $photo->url,
                    'guest_name' => $photo->guest?->firstname ?? $photo->uploaded_by ?? 'Admin',
                    'created_at' => $photo->created_at->format('d.m.Y H:i'),
                ])
            : collect();

        return Inertia::render('Photos/Index', [
            'photos' => $photos,
        ]);
    }

    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png', 'max:10240'],
        ]);

        $extension = $request->file('photo')->getClientOriginalExtension();
        $path      = 'photos/' . Str::uuid() . '.' . $extension;

        Storage::disk('s3')->put($path, file_get_contents($request->file('photo')), 'public');

        Photo::create([
            'event_id'    => $event?->id,
            'guest_id'    => null,
            'uploaded_by' => $request->user()->name,
            'url'         => Storage::disk('s3')->url($path),
        ]);

        return back();
    }

    public function destroy(Photo $photo)
    {
        $path = parse_url($photo->url, PHP_URL_PATH);
        Storage::disk('s3')->delete(ltrim($path, '/'));
        $photo->delete();

        return back();
    }
}
