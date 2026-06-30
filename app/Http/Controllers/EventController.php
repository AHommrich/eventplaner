<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\PhotoAlbum;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Event-Lifecycle und Onboarding-Flow für Web-User.
 *
 *  - `onboarding()` / `noEvent()` → Einstiegs-Seiten für User ohne Event
 *  - `store()`                    → nur Admin; erstellt Event + 3 Standard-Alben + projector_token
 *  - `requestEvent()`             → Nicht-Admin reicht Event-Wunsch ein (EventRequest::pending)
 *  - `switch()`                   → wechselt das aktive Event in der Session (siehe {@see Controller::activeEvent()})
 *
 * Default-Alben werden zentral via {@see self::createDefaultAlbums()} angelegt und
 * auch von {@see RequestController::approveEventRequest()} wiederverwendet.
 */
class EventController extends Controller
{
    public function onboarding()
    {
        // Nur Admins dürfen Events direkt erstellen
        if (! auth()->user()->isAdmin()) {
            return redirect()->route('no-event');
        }

        return Inertia::render('Onboarding');
    }

    public function noEvent()
    {
        $user = auth()->user();

        // Hat bereits ein Event → App
        if ($user->isAdmin() || $user->accessibleEvents()->exists()) {
            return redirect()->route('dashboard');
        }

        $hasPendingRequest = EventRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        return Inertia::render('NoEvent', [
            'hasPendingRequest' => $hasPendingRequest,
        ]);
    }

    public function store(Request $request)
    {
        // Nur Admins dürfen Events direkt erstellen
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'nullable|date',
        ]);

        $event = Event::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'date' => $data['date'] ?? null,
            'projector_token' => Str::random(32),
        ]);

        $this->createDefaultAlbums($event->id);

        session(['active_event_id' => $event->id]);

        return redirect()->route('dashboard');
    }

    public function requestEvent(Request $request)
    {
        $user = auth()->user();

        if (EventRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return redirect()->back();
        }

        $data = $request->validate([
            'event_name' => 'required|string|max:255',
        ]);

        EventRequest::create([
            'user_id' => $user->id,
            'event_name' => $data['event_name'],
            'status' => 'pending',
        ]);

        return redirect()->route('no-event');
    }

    public function switch(Request $request)
    {
        $data = $request->validate(['event_id' => 'required|integer']);

        $user = auth()->user();
        $events = $user->accessibleEvents()->get();
        $event = $events->firstWhere('id', $data['event_id']);

        if (! $event) {
            return redirect()->back()->with('error', 'Event nicht gefunden.');
        }

        $request->session()->put('active_event_id', $event->id);

        return Inertia::location(back()->getTargetUrl());
    }

    public static function createDefaultAlbums(int $eventId): void
    {
        $albums = [
            ['slug' => PhotoAlbum::APP_GALLERY,   'name' => 'App Galerie',  'sort_order' => 1],
            ['slug' => PhotoAlbum::PRESENTATION,  'name' => 'Präsentation', 'sort_order' => 2],
            ['slug' => PhotoAlbum::PHOTO_GAME,    'name' => 'Fotospiel',    'sort_order' => 3],
        ];

        foreach ($albums as $album) {
            PhotoAlbum::firstOrCreate(
                ['event_id' => $eventId, 'slug' => $album['slug']],
                ['name' => $album['name'], 'sort_order' => $album['sort_order']]
            );
        }
    }
}
