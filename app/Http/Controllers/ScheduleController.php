<?php

namespace App\Http\Controllers;

use App\Models\ScheduleItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Organizer view on the event schedule (stations shown in the guest app).
 * Each station has a time, a title and an optional structured location. Order
 * is explicit via sort_order; per-group visibility lives on the group
 * (schedule_visible_from) and is applied when the guest app reads the schedule.
 */
class ScheduleController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        $items = $event
            ? $event->scheduleItems()->get()->map(fn (ScheduleItem $i) => $this->present($i))
            : collect();

        $groups = $event
            ? $event->groups()->orderBy('name')->get()->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'schedule_visible_from' => $g->schedule_visible_from ? substr($g->schedule_visible_from, 0, 5) : null,
            ])
            : collect();

        return Inertia::render('Schedule/Index', [
            'items' => $items->values(),
            'groups' => $groups->values(),
        ]);
    }

    public function store(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $this->validated($request);
        $data['event_id'] = $event->id;
        $data['sort_order'] = (int) $event->scheduleItems()->max('sort_order') + 1;

        ScheduleItem::create($data);

        return redirect()->back()->with('success', true);
    }

    public function update(Request $request, ScheduleItem $scheduleItem)
    {
        $this->authorizeItem($scheduleItem);

        $scheduleItem->update($this->validated($request));

        return redirect()->back()->with('success', true);
    }

    public function destroy(ScheduleItem $scheduleItem)
    {
        $this->authorizeItem($scheduleItem);

        $scheduleItem->delete();

        return redirect()->back()->with('success', true);
    }

    public function reorder(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'ids' => 'array',
            'ids.*' => 'integer',
        ]);

        foreach ($data['ids'] as $index => $id) {
            $event->scheduleItems()->where('id', $id)->update(['sort_order' => $index]);
        }

        return redirect()->back()->with('success', true);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'starts_at' => 'nullable|date_format:H:i',
            'location_name' => 'nullable|string|max:255',
            'location_street' => 'nullable|string|max:255',
            'location_house_number' => 'nullable|string|max:20',
            'location_postal_code' => 'nullable|string|max:20',
            'location_city' => 'nullable|string|max:255',
            'location_state' => 'nullable|string|max:255',
            'location_country' => 'nullable|string|max:100',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
        ]);
    }

    /**
     * Guard: the station must belong to the caller's active event.
     */
    private function authorizeItem(ScheduleItem $item): void
    {
        $event = $this->activeEvent();
        abort_if(! $event || $item->event_id !== $event->id, 404);
    }

    private function present(ScheduleItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            // Trim the DB "HH:MM:SS" down to the "HH:MM" the <input type="time"> uses.
            'starts_at' => $item->starts_at ? substr($item->starts_at, 0, 5) : null,
            'sort_order' => $item->sort_order,
            'location_name' => $item->location_name,
            'location_street' => $item->location_street,
            'location_house_number' => $item->location_house_number,
            'location_postal_code' => $item->location_postal_code,
            'location_city' => $item->location_city,
            'location_state' => $item->location_state,
            'location_country' => $item->location_country,
            'location_lat' => $item->location_lat,
            'location_lng' => $item->location_lng,
        ];
    }
}
