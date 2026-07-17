<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ScheduleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only schedule for the bound organizer event.
 *
 * Unlike the guest event-info endpoint, the organizer receives the event's
 * **complete** schedule — no group-visibility filtering — because editing stays
 * in the web app and organizers need the full picture. There is deliberately no
 * mutation route: the schedule is edited only on the web.
 */
class ManagementScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $event = $this->event($request);

        $stations = $event->scheduleItems()->get()->map(fn (ScheduleItem $s) => [
            'id' => $s->id,
            'title' => $s->title,
            // "HH:MM" — the app composes the absolute moment with the event date.
            'starts_at' => $s->starts_at ? substr($s->starts_at, 0, 5) : null,
            'ends_at' => $s->ends_at ? substr($s->ends_at, 0, 5) : null,
            'location_name' => $s->location_name,
            'address' => $this->assembleAddress($s),
            'lat' => $s->location_lat,
            'lng' => $s->location_lng,
        ])->values();

        return response()->json([
            'date' => optional($event->date)->toDateString(),
            'schedule' => $event->schedule,
            'schedule_stations' => $stations,
        ]);
    }

    private function assembleAddress(ScheduleItem $s): ?string
    {
        $street = trim(implode(' ', array_filter([$s->location_street, $s->location_house_number])));
        $city = trim(implode(' ', array_filter([$s->location_postal_code, $s->location_city])));
        $parts = array_filter([$street, $city, $s->location_state, $s->location_country]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function event(Request $request): Event
    {
        return $request->attributes->get('management_event');
    }
}
