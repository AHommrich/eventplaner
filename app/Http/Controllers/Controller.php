<?php

namespace App\Http\Controllers;

use App\Models\Event;

abstract class Controller
{
    /**
     * Returns the currently selected event of the user (session-based).
     *
     * Reads `session('active_event_id')`, falls back to the first accessible event
     * if the session is empty or the stored event is no longer accessible, and
     * persists the choice back to the session.
     *
     * Mirrored by {@see \App\Http\Middleware\HandleInertiaRequests}, which
     * shares `active_event` globally with all Vue pages — controller methods can
     * rely on knowing the event without additional request parameters.
     * Null only when the user has no event yet (→ onboarding flow).
     */
    protected function activeEvent(): ?Event
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $query = $user->accessibleEvents();
        $events = $query->get();

        if ($events->isEmpty()) {
            return null;
        }

        $sessionId = session('active_event_id');
        $event = $sessionId ? $events->firstWhere('id', $sessionId) : null;

        // fallback to first event if the session value is not accessible
        $event ??= $events->first();

        session(['active_event_id' => $event->id]);

        return $event;
    }
}
