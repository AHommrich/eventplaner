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

        $isGermany = !$event->venue_country ||
            in_array(strtolower($event->venue_country), ['deutschland', 'germany', 'de']);

        // Adresse aus strukturierten Feldern zusammenbauen
        $assembledAddress = null;
        if ($event->venue_street || $event->venue_city) {
            if ($isGermany) {
                $street = trim(($event->venue_street ?? '') . ' ' . ($event->venue_house_number ?? ''));
                $city   = trim(($event->venue_postal_code ?? '') . ' ' . ($event->venue_city ?? ''));
                $parts  = array_filter([$street, $city]);
            } else {
                $street = trim(($event->venue_street ?? '') . ' ' . ($event->venue_house_number ?? ''));
                $parts  = array_filter([
                    $street,
                    $event->venue_city,
                    $event->venue_state,
                    $event->venue_postal_code,
                    $event->venue_country,
                ]);
            }
            $assembledAddress = implode(', ', $parts) ?: null;
        } elseif ($event->venue_address) {
            // Fallback auf altes Freitextfeld
            $assembledAddress = $event->venue_address;
        }

        return response()->json([
            'name'            => $event->name,
            'date'            => $event->date ? Carbon::parse($event->date)->toIso8601String() : null,
            'rsvp_deadline'   => $event->rsvp_deadline ? Carbon::parse($event->rsvp_deadline)->toIso8601String() : null,
            'cover_image_url' => $event->cover_image_url,
            'venue_name'      => $event->venue_name,
            'venue_address'   => $assembledAddress,
            'venue_lat'       => $event->venue_lat,
            'venue_lng'       => $event->venue_lng,
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
            'color_fab_icon'         => $r('role_fab_icon',         'tertiary'),
            'color_home_text'        => $event->color_home_text,
            'font_heading'           => $event->font_heading,
            'drink_game_enabled'     => (bool) $event->drink_game_enabled,
            'drink_game_end_time'    => $event->drink_game_end_time ? Carbon::parse($event->drink_game_end_time)->toIso8601String() : null,
        ]);
    }
}
