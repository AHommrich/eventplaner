<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventAccessController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        if (! $event) {
            return redirect()->route('dashboard');
        }

        // Nur Owner und Superadmin dürfen Zugang verwalten
        $user = auth()->user();
        if (! $user->isAdmin() && $event->user_id !== $user->id) {
            abort(403);
        }

        $members = $event->users()->get(['users.id', 'users.name', 'users.email']);
        $owner = $event->owner;

        return Inertia::render('Event/Access', [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'owner' => ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email],
            'members' => $members,
        ]);
    }

    public function invite(Request $request)
    {
        $event = $this->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Kein aktives Event.');
        }

        $user = auth()->user();
        if (! $user->isAdmin() && $event->user_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $invitee = User::where('email', $data['email'])->first();

        if ($invitee->id === $event->user_id) {
            return redirect()->back()->with('error', 'Der Owner ist bereits in diesem Event.');
        }

        $event->users()->syncWithoutDetaching([$invitee->id]);

        return redirect()->back()->with('success', "{$invitee->name} wurde hinzugefügt.");
    }

    public function remove(Request $request, User $user)
    {
        $event = $this->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Kein aktives Event.');
        }

        $authUser = auth()->user();
        if (! $authUser->isAdmin() && $event->user_id !== $authUser->id) {
            abort(403);
        }

        if ($user->id === $event->user_id) {
            return redirect()->back()->with('error', 'Der Owner kann nicht entfernt werden.');
        }

        $event->users()->detach($user->id);

        return redirect()->back()->with('success', "{$user->name} wurde entfernt.");
    }
}
