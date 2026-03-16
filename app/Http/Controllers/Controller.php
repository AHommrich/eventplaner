<?php

namespace App\Http\Controllers;

use App\Models\Event;

abstract class Controller
{
    /**
     * Gibt das aktive Event zurück — aus Session oder erstes zugängliches.
     * Speichert die Wahl in der Session für folgende Requests.
     */
    protected function activeEvent(): ?Event
    {
        $user = auth()->user();
        if (!$user) return null;

        $query = $user->accessibleEvents();
        $events = $query->get();

        if ($events->isEmpty()) return null;

        $sessionId = session('active_event_id');
        $event = $sessionId ? $events->firstWhere('id', $sessionId) : null;

        // Fallback auf erstes Event wenn Session-Wert nicht zugänglich
        $event ??= $events->first();

        session(['active_event_id' => $event->id]);

        return $event;
    }
}
