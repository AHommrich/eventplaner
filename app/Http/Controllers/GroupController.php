<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $request->validate(['name' => 'required|string|max:255']);

        $group = Group::create([
            'event_id' => $event?->id,
            'name'     => $request->name,
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
}
