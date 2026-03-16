<?php

namespace App\Http\Controllers;

use App\Models\Drink;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DrinkController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        return Inertia::render('Drinks/Index', [
            'drinks' => $event ? $event->drinks()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Drink::create([
            'event_id' => $event?->id,
            'name'     => $data['name'],
        ]);

        return redirect()->back()->with('success', 'Getränk hinzugefügt.');
    }

    public function destroy(Drink $drink)
    {
        $drink->delete();
        return redirect()->back()->with('success', 'Getränk gelöscht.');
    }
}
