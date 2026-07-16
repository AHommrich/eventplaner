<?php

namespace App\Http\Controllers;

use App\Models\FoodSpecial;
use Illuminate\Http\Request;

class FoodSpecialController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        // Always event-local: a manager can never mutate the shared template
        // catalog (event_id = null) or another event's entries.
        $foodSpecial = FoodSpecial::create([
            'event_id' => $this->activeEvent()?->id,
            'name' => $request->name,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $foodSpecial->id, 'name' => $foodSpecial->name]);
        }

        return redirect()->back()->with('success', 'Food Special erstellt!');
    }
}
