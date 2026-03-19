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
            // Neue Theme-Felder (4 klar definierte Rollen)
            'color_accent'        => $event->color_accent     ?? '#7c2d3e',
            'color_background'    => $event->color_background ?? '#e8e3de',
            'color_card'          => $event->color_card        ?? '#ffffff',
            'color_home_text'     => $event->color_home_text   ?? '#ffffff',
            'color_card_text'     => $event->color_card_text   ?? $event->color_accent ?? '#7c2d3e',
            'color_tab_tint'      => $event->color_tab_tint    ?? $event->color_accent ?? '#7c2d3e',
            // Alte Felder bleiben für Übergangszeit (App-Rückwärtskompatibilität)
            'color_primary'       => $event->color_primary,
            'color_secondary'     => $event->color_secondary,
            'drink_game_enabled'   => (bool) $event->drink_game_enabled,
            'drink_game_end_time'  => $event->drink_game_end_time ? Carbon::parse($event->drink_game_end_time)->toIso8601String() : null,
        ]);
    }
}
