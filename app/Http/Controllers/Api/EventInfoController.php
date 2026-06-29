<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ColorRoleResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liefert die für den Gast relevanten Stamm-Infos seines Events.
 *
 * Format:
 *  - Adresse wird aus den strukturierten Venue-Feldern zusammengebaut
 *    (DE-Reihenfolge anders als International). Legacy-Fallback auf
 *    `venue_address` (Freitext).
 *  - Farben: Palette unverändert + alle 9 Rollen bereits zu Hex aufgelöst
 *    (siehe {@see ColorRoleResolver}), damit Clients nichts mappen müssen.
 *  - Datumsfelder als ISO-8601.
 */
class EventInfoController extends Controller
{
    public function __construct(private readonly ColorRoleResolver $colors) {}

    /**
     * GET /api/event/info
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();
        $event = $guest->event;

        abort_if(!$event, 404, 'Kein Event gefunden.');

        $colors = $this->colors->resolve($event);

        return response()->json([
            'name'            => $event->name,
            'date'            => $event->date ? Carbon::parse($event->date)->toIso8601String() : null,
            'rsvp_deadline'   => $event->rsvp_deadline ? Carbon::parse($event->rsvp_deadline)->toIso8601String() : null,
            'cover_image_url' => $event->cover_image_url,
            'venue_name'      => $event->venue_name,
            'venue_address'   => $this->assembleVenueAddress($event),
            'venue_lat'       => $event->venue_lat,
            'venue_lng'       => $event->venue_lng,
            'dresscode'       => $event->dresscode,
            'schedule'        => $event->schedule,
            // Palette
            'color_primary'   => $colors['palette']['primary'],
            'color_secondary' => $colors['palette']['secondary'],
            'color_tertiary'  => $colors['palette']['tertiary'],
            // Aufgelöste Rollen
            'color_screen_bg'        => $colors['roles']['role_screen_bg'],
            'color_card'             => $colors['roles']['role_card_bg'],
            'color_card_text'        => $colors['roles']['role_card_text'],
            'color_card_button'      => $colors['roles']['role_card_button'],
            'color_card_button_text' => $colors['roles']['role_card_button_text'],
            'color_tab_tint'         => $colors['roles']['role_tab_tint'],
            'color_border'           => $colors['roles']['role_border'],
            'color_fab'              => $colors['roles']['role_fab'],
            'color_fab_icon'         => $colors['roles']['role_fab_icon'],
            // Cover-Overlay
            'color_home_text'        => $colors['cover']['home_text'],
            'color_home_shadow'      => $colors['cover']['home_shadow'],
            'home_shadow_opacity'    => $colors['cover']['home_shadow_opacity'],
            'font_heading'           => $event->font_heading,
            'venue_display_mode'     => $event->venue_display_mode ?? 'both',
            'drink_game_enabled'     => (bool) $event->drink_game_enabled,
            'drink_game_end_time'    => $event->drink_game_end_time ? Carbon::parse($event->drink_game_end_time)->toIso8601String() : null,
            'photo_game_enabled'     => (bool) $event->photo_game_enabled,
        ]);
    }

    private function assembleVenueAddress(\App\Models\Event $event): ?string
    {
        $isGermany = !$event->venue_country ||
            in_array(strtolower($event->venue_country), ['deutschland', 'germany', 'de']);

        if ($event->venue_street || $event->venue_city) {
            $street = trim(($event->venue_street ?? '') . ' ' . ($event->venue_house_number ?? ''));

            if ($isGermany) {
                $city  = trim(($event->venue_postal_code ?? '') . ' ' . ($event->venue_city ?? ''));
                $parts = array_filter([$street, $city]);
            } else {
                $parts = array_filter([
                    $street,
                    $event->venue_city,
                    $event->venue_state,
                    $event->venue_postal_code,
                    $event->venue_country,
                ]);
            }
            return implode(', ', $parts) ?: null;
        }

        // Fallback auf altes Freitextfeld
        return $event->venue_address ?: null;
    }
}
