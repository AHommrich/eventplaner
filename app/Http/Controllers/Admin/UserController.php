<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users', [
            'users'  => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']),
            'events' => Event::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'required|in:admin,user',
        ]);

        $user->update($data);

        return redirect()->back()->with('success', 'Rolle aktualisiert.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'Du kannst dich nicht selbst löschen.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User gelöscht.');
    }

    public function addToEvent(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email|exists:users,email',
            'event_id' => 'required|exists:events,id',
        ]);

        $user  = User::where('email', $data['email'])->first();
        $event = Event::find($data['event_id']);

        // Nicht doppelt hinzufügen
        $event->users()->syncWithoutDetaching([$user->id]);

        return redirect()->back()->with('success', "{$user->name} wurde zum Event hinzugefügt.");
    }
}
