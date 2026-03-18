<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventInfoController extends Controller
{
    /**
     * GET /api/event/info
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();
        $event = $guest->event;

        abort_if(!$event, 404, 'Kein Event gefunden.');

        return response()->json([
            'name'             => $event->name,
            'date'             => $event->date ? Carbon::parse($event->date)->toIso8601String() : null,
            'rsvp_deadline'    => $event->rsvp_deadline ? Carbon::parse($event->rsvp_deadline)->toIso8601String() : null,
            'cover_image_url'  => $event->cover_image_url,
            'venue_name'       => $event->venue_name,
            'venue_address'    => $event->venue_address,
            'dresscode'        => $event->dresscode,
            'schedule'         => $event->schedule,
            'color_primary'       => $event->color_primary,
            'color_secondary'     => $event->color_secondary,
            'color_home_text'     => $event->color_home_text,
            'drink_game_enabled'   => (bool) $event->drink_game_enabled,
            'drink_game_end_time'  => $event->drink_game_end_time ? Carbon::parse($event->drink_game_end_time)->toIso8601String() : null,
        ]);
    }
}
