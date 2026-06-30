<?php

namespace App\Http\Controllers;

use App\Models\Event;

abstract class Controller
{
    /**
     * Liefert das aktuell gewählte Event des Users (Session-basiert).
     *
     * Liest `session('active_event_id')`, fällt auf das erste zugängliche Event
     * zurück, falls die Session leer ist oder das gespeicherte Event nicht mehr
     * zugänglich ist, und persistiert die Wahl wieder in die Session.
     *
     * Wird gespiegelt von {@see \App\Http\Middleware\HandleInertiaRequests}, die
     * `active_event` global an alle Vue-Pages teilt — Controller-Methoden können
     * sich darauf verlassen, das Event ohne zusätzliche Request-Parameter zu kennen.
     * Null nur, wenn der User noch kein Event hat (→ Onboarding-Flow).
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

        // Fallback auf erstes Event wenn Session-Wert nicht zugänglich
        $event ??= $events->first();

        session(['active_event_id' => $event->id]);

        return $event;
    }
}
