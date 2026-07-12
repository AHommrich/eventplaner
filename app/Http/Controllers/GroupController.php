<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

/**
 * Manages family / group units within an event.
 *
 * `store()` supports both Inertia redirects and JSON responses
 * (for the CreatableCombobox on the frontend, which creates groups on-the-fly).
 * `destroy()` checks the event scope and cascades the associated guests via FK.
 */
class GroupController extends Controller
{
    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $request->validate(['name' => 'required|string|max:255']);

        $group = Group::create([
            'event_id' => $event?->id,
            'name' => $request->name,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $group->id, 'name' => $group->name]);
        }

        return redirect()->back()->with('success', 'Gruppe erstellt!');
    }

    public function destroy(Group $group)
    {
        $event = $this->activeEvent();
        abort_if($group->event_id !== $event?->id, 403);

        $group->delete();

        return redirect()->back()->with('success', 'Gruppe gelöscht.');
    }

    /**
     * Sets a group's schedule visibility cutoff — the guest app then only
     * shows this group stations at/after the given time. Null = sees all.
     */
    public function updateScheduleVisibility(Request $request, Group $group)
    {
        $event = $this->activeEvent();
        abort_if($group->event_id !== $event?->id, 403);

        $data = $request->validate([
            'schedule_visible_from' => 'nullable|date_format:H:i',
        ]);

        $group->update(['schedule_visible_from' => $data['schedule_visible_from'] ?? null]);

        return redirect()->back()->with('success', true);
    }
}
