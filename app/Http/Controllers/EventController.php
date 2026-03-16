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

        // Hat der User bereits Zugriff auf ein Event → weiterleiten
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

        Event::create([
            'user_id' => auth()->id(),
            'name'    => $data['name'],
            'date'    => $data['date'] ?? null,
        ]);

        return redirect()->route('dashboard');
    }
}
