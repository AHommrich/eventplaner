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

        $nameMode = $event->projector_name_mode ?? 'first';

        $photos = $album
            ? $this->buildProjectorPhotos($album, $nameMode)
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

        $nameMode = $event->projector_name_mode ?? 'first';

        $photos = $album
            ? $this->buildProjectorPhotos($album, $nameMode)
            : collect();

        return response()->json(['data' => $photos]);
    }

    private function buildProjectorPhotos($album, string $nameMode = 'first'): \Illuminate\Support\Collection
    {
        $query = $album->photos()->latest();

        if ($album->slug === 'app_gallery') {
            $query->with('guest');
        } elseif ($album->slug === 'photo_game') {
            $query->with(['gameAssignment.task', 'gameAssignment.override']);
        }

        return $query->get()->map(function ($photo) use ($album, $nameMode) {
            $label = match ($album->slug) {
                'app_gallery' => $this->resolveGuestName($photo, $nameMode),
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

    private function resolveGuestName($photo, string $nameMode): ?string
    {
        if ($nameMode === 'none') return null;

        if ($photo->guest) {
            return $nameMode === 'first'
                ? ($photo->guest->firstname ?? null)
                : trim(($photo->guest->firstname ?? '') . ' ' . ($photo->guest->lastname ?? ''));
        }

        $uploaderName = $photo->uploaded_by ?? null;
        if (!$uploaderName) return null;

        return $nameMode === 'first'
            ? explode(' ', $uploaderName)[0]
            : $uploaderName;
    }
}
