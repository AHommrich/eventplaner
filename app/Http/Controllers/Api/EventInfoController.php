<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventInfoController extends Controller
{
    /**
     * GET /api/event/info
     * Gibt Event-Basisinfos zurück, inkl. rsvp_deadline.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();
        $event = $guest->event;

        abort_if(!$event, 404, 'Kein Event gefunden.');

        return response()->json([
            'name'          => $event->name,
            'date'          => $event->date,
            'rsvp_deadline' => $event->rsvp_deadline ? \Carbon\Carbon::parse($event->rsvp_deadline)->toIso8601String() : null,
        ]);
    }
}
