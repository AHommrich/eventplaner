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

        $palette = [
            'primary'   => $event->color_primary   ?? '#7c2d3e',
            'secondary' => $event->color_secondary ?? '#e8e3de',
            'tertiary'  => $event->color_tertiary  ?? '#ffffff',
        ];
        $r = fn(string $field, string $fallback): string =>
            $palette[$event->$field ?? $fallback] ?? $palette[$fallback];

        return response()->json([
            'name'            => $event->name,
            'date'            => $event->date ? Carbon::parse($event->date)->toIso8601String() : null,
            'rsvp_deadline'   => $event->rsvp_deadline ? Carbon::parse($event->rsvp_deadline)->toIso8601String() : null,
            'cover_image_url' => $event->cover_image_url,
            'venue_name'      => $event->venue_name,
            'venue_address'   => $event->venue_address,
            'dresscode'       => $event->dresscode,
            'schedule'        => $event->schedule,
            // Palette
            'color_primary'   => $palette['primary'],
            'color_secondary' => $palette['secondary'],
            'color_tertiary'  => $palette['tertiary'],
            // Aufgelöste Rollen
            'color_screen_bg'        => $r('role_screen_bg',        'secondary'),
            'color_card'             => $r('role_card_bg',          'tertiary'),
            'color_card_text'        => $r('role_card_text',        'primary'),
            'color_card_button'      => $r('role_card_button',      'primary'),
            'color_card_button_text' => $r('role_card_button_text', 'tertiary'),
            'color_tab_tint'         => $r('role_tab_tint',         'primary'),
            'color_border'           => $r('role_border',           'primary'),
            'color_fab'              => $r('role_fab',              'primary'),
            'color_home_text'        => $event->color_home_text,
            'font_heading'           => $event->font_heading,
            'drink_game_enabled'     => (bool) $event->drink_game_enabled,
            'drink_game_end_time'    => $event->drink_game_end_time ? Carbon::parse($event->drink_game_end_time)->toIso8601String() : null,
        ]);
    }
}
