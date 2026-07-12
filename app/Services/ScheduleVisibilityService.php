<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Support\Collection;

/**
 * Decides which schedule stations a guest may see, based on their group's
 * hidden-station set (group_schedule_item_hidden).
 *
 * Rule:
 *  - No group (family, close friends, solo guests without a group) sees every
 *    station.
 *  - A group sees every station except the ones explicitly hidden from it. This
 *    supports arbitrary gaps — e.g. invited to the ceremony and the party but
 *    not the lunch in between — which a single "from time X" cutoff could not.
 */
class ScheduleVisibilityService
{
    public function visibleStations(Event $event, ?Group $group): Collection
    {
        $stations = $event->scheduleItems()->get();

        if ($group === null) {
            return $stations;
        }

        $hidden = $group->hiddenScheduleItems()->pluck('schedule_items.id')->all();

        return $stations->reject(fn ($station) => in_array($station->id, $hidden, true))->values();
    }
}
