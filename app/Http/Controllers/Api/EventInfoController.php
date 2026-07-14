<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleItem;
use App\Services\ColorRoleResolver;
use App\Services\ScheduleVisibilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns the core event info relevant to the guest.
 *
 * Format:
 *  - Address is assembled from the structured venue fields
 *    (DE order differs from international). Legacy fallback to
 *    `venue_address` (free text).
 *  - Colors: palette unchanged + all 9 roles already resolved to hex
 *    (see {@see ColorRoleResolver}) so clients don't need to map anything.
 *  - Date fields as ISO-8601.
 */
class EventInfoController extends Controller
{
    public function __construct(
        private readonly ColorRoleResolver $colors,
        private readonly ScheduleVisibilityService $schedule,
    ) {}

    /**
     * GET /api/event/info
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();
        $event = $guest->event;

        abort_if(! $event, 404, 'Kein Event gefunden.');

        $colors = $this->colors->resolve($event);

        $stations = $this->schedule->visibleStations($event, $guest->group);

        return response()->json([
            'name' => $event->name,
            'date' => $event->date ? Carbon::parse($event->date)->toIso8601String() : null,
            'rsvp_deadline' => $event->rsvp_deadline ? Carbon::parse($event->rsvp_deadline)->toIso8601String() : null,
            'cover_image_url' => $event->cover_image_url,
            'venue_name' => $event->venue_name,
            'venue_address' => $this->assembleVenueAddress($event),
            'venue_lat' => $event->venue_lat,
            'venue_lng' => $event->venue_lng,
            'dresscode' => $event->dresscode,
            'schedule' => $event->schedule,
            'schedule_stations' => $stations->map(fn (ScheduleItem $s) => [
                'id' => $s->id,
                'title' => $s->title,
                // "HH:MM" — the guest app composes the absolute moment with `date`.
                'starts_at' => $s->starts_at ? substr($s->starts_at, 0, 5) : null,
                'ends_at' => $s->ends_at ? substr($s->ends_at, 0, 5) : null,
                'location_name' => $s->location_name,
                'address' => $this->assembleAddress(
                    $s->location_street,
                    $s->location_house_number,
                    $s->location_postal_code,
                    $s->location_city,
                    $s->location_state,
                    $s->location_country,
                ),
                'lat' => $s->location_lat,
                'lng' => $s->location_lng,
            ])->values(),
            // palette
            'color_primary' => $colors['palette']['primary'],
            'color_secondary' => $colors['palette']['secondary'],
            'color_tertiary' => $colors['palette']['tertiary'],
            // resolved roles
            'color_screen_bg' => $colors['roles']['role_screen_bg'],
            'color_card' => $colors['roles']['role_card_bg'],
            'color_card_text' => $colors['roles']['role_card_text'],
            'color_card_button' => $colors['roles']['role_card_button'],
            'color_card_button_text' => $colors['roles']['role_card_button_text'],
            'color_tab_tint' => $colors['roles']['role_tab_tint'],
            'color_border' => $colors['roles']['role_border'],
            'color_fab' => $colors['roles']['role_fab'],
            'color_fab_icon' => $colors['roles']['role_fab_icon'],
            // cover overlay
            'color_home_text' => $colors['cover']['home_text'],
            'color_home_shadow' => $colors['cover']['home_shadow'],
            'home_shadow_opacity' => $colors['cover']['home_shadow_opacity'],
            'font_heading' => $event->font_heading,
            'design_preset' => $event->design_preset ?? 'classic',
            'venue_display_mode' => $event->venue_display_mode ?? 'both',
            'drink_game_enabled' => (bool) $event->drink_game_enabled,
            'drink_game_end_time' => $event->drink_game_end_time ? Carbon::parse($event->drink_game_end_time)->toIso8601String() : null,
            'photo_game_enabled' => (bool) $event->photo_game_enabled,
        ]);
    }

    private function assembleVenueAddress(\App\Models\Event $event): ?string
    {
        return $this->assembleAddress(
            $event->venue_street,
            $event->venue_house_number,
            $event->venue_postal_code,
            $event->venue_city,
            $event->venue_state,
            $event->venue_country,
            // fallback to legacy free-text field
        ) ?? ($event->venue_address ?: null);
    }

    /**
     * Assembles a display address from structured fields. DE order
     * ("Street 1, 12345 City") differs from the international order.
     * Returns null when there is nothing to show.
     */
    private function assembleAddress(
        ?string $street,
        ?string $houseNumber,
        ?string $postalCode,
        ?string $city,
        ?string $state,
        ?string $country,
    ): ?string {
        if (! $street && ! $city) {
            return null;
        }

        $isGermany = ! $country || in_array(strtolower($country), ['deutschland', 'germany', 'de']);
        $streetLine = trim(($street ?? '').' '.($houseNumber ?? ''));

        if ($isGermany) {
            $cityLine = trim(($postalCode ?? '').' '.($city ?? ''));
            $parts = array_filter([$streetLine, $cityLine]);
        } else {
            $parts = array_filter([$streetLine, $city, $state, $postalCode, $country]);
        }

        return implode(', ', $parts) ?: null;
    }
}
