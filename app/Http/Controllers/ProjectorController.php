<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

class ProjectorController extends Controller
{
    public function show(string $token)
    {
        $event = Event::where('projector_token', $token)->firstOrFail();

        $album = $event->projectorAlbum;

        $photos = $album
            ? $album->photos()->with('guest')->latest()->get()->map(fn($photo) => [
                'id'  => $photo->id,
                'url' => $photo->url,
            ])
            : collect();

        return Inertia::render('Projector/Show', [
            'event'  => ['name' => $event->name],
            'photos' => $photos,
            'token'  => $token,
        ]);
    }

    public function photos(string $token)
    {
        $event = Event::where('projector_token', $token)->firstOrFail();

        $album = $event->projectorAlbum;

        $photos = $album
            ? $album->photos()->latest()->get()->map(fn($photo) => [
                'id'  => $photo->id,
                'url' => $photo->url,
            ])
            : collect();

        return response()->json(['data' => $photos]);
    }
}
