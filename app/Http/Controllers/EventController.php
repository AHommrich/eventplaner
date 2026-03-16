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

        // Admins direkt zum Dashboard
        if ($user->isAdmin()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding', [
            'email'    => $user->email,
            'hasEvent' => $user->accessibleEvents()->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'nullable|date',
        ]);

        Event::create([
            'user_id' => $user->id,
            'name'    => $data['name'],
            'date'    => $data['date'] ?? null,
        ]);

        // Admins zum Dashboard, normale User zurück zum Onboarding (kein Admin-Zugriff)
        if ($user->isAdmin()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding')->with('success', 'Event erstellt!');
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
