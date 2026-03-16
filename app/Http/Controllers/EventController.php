<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function onboarding()
    {
        $user = auth()->user();

        // Hat bereits Zugriff auf ein Event → direkt zur App
        if ($user->isAdmin() || $user->accessibleEvents()->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding', [
            'email' => $user->email,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'nullable|date',
        ]);

        $event = Event::create([
            'user_id' => auth()->id(),
            'name'    => $data['name'],
            'date'    => $data['date'] ?? null,
        ]);

        // Neu erstelltes Event direkt als aktives Event setzen
        session(['active_event_id' => $event->id]);

        return redirect()->route('dashboard');
    }

    public function switch(Request $request)
    {
        $data = $request->validate(['event_id' => 'required|integer']);

        $user   = auth()->user();
        $events = $user->accessibleEvents()->get();
        $event  = $events->firstWhere('id', $data['event_id']);

        if (!$event) {
            return redirect()->back()->with('error', 'Event nicht gefunden.');
        }

        $request->session()->put('active_event_id', $event->id);

        return redirect()->back()->with('success', 'Event gewechselt.');
    }
}
