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

        Group::create([
            'event_id' => $event?->id,
            'name'     => $request->name,
        ]);

        return redirect()->back()->with('success', 'Gruppe erstellt!');
    }
}
