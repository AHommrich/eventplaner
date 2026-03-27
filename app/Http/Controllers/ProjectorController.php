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
            ? $this->buildProjectorPhotos($album)
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
            ? $this->buildProjectorPhotos($album)
            : collect();

        return response()->json(['data' => $photos]);
    }

    private function buildProjectorPhotos($album): \Illuminate\Support\Collection
    {
        $query = $album->photos()->latest();

        if ($album->slug === 'app_gallery') {
            $query->with('guest');
        } elseif ($album->slug === 'photo_game') {
            $query->with(['gameAssignment.task', 'gameAssignment.override']);
        }

        return $query->get()->map(function ($photo) use ($album) {
            $label = match ($album->slug) {
                'app_gallery' => $photo->guest
                    ? trim(($photo->guest->firstname ?? '') . ' ' . ($photo->guest->lastname ?? ''))
                    : ($photo->uploaded_by ?? null),
                'presentation' => $photo->description ?: null,
                'photo_game'   => $photo->gameAssignment?->override?->custom_text
                    ?? $photo->gameAssignment?->task?->description
                    ?? null,
                default => null,
            };

            return [
                'id'    => $photo->id,
                'url'   => $photo->url,
                'label' => $label ?: null,
            ];
        });
    }
}
