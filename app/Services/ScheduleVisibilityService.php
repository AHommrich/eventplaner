<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Support\Collection;

/**
 * Decides which schedule stations a guest may see, based on their group's
 * visibility cutoff (groups.schedule_visible_from).
 *
 * Rule:
 *  - A null cutoff (family, close friends, solo guests without a group) sees
 *    every station.
 *  - A set cutoff (e.g. work colleagues invited from 19:00) sees only stations
 *    with a defined time at/after the cutoff. Timeless stations stay hidden
 *    from restricted groups, so a forgotten time can never leak an earlier
 *    stop (like the registry office and its address).
 */
class ScheduleVisibilityService
{
    public function visibleStations(Event $event, ?Group $group): Collection
    {
        $cutoff = $group?->schedule_visible_from;

        return $event->scheduleItems()
            ->when(
                $cutoff !== null,
                fn ($q) => $q->whereNotNull('starts_at')->where('starts_at', '>=', $cutoff),
            )
            ->get();
    }
}
