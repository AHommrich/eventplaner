<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $selectedId = $request->query('event_id');
        $targetEvent = $selectedId ? Event::find($selectedId) : $this->activeEvent();

        $eventAccess = null;
        if ($targetEvent) {
            $owner = $targetEvent->owner;
            $eventAccess = [
                'event' => ['id' => $targetEvent->id, 'name' => $targetEvent->name],
                'owner' => $owner ? ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email] : null,
                'members' => $targetEvent->users()->get(['users.id', 'users.name', 'users.email'])->toArray(),
            ];
        }

        return Inertia::render('Admin/Users', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']),
            'events' => Event::orderBy('name')->get(['id', 'name']),
            'event_access' => $eventAccess,
        ]);
    }

    public function removeFromEvent(Request $request, User $user)
    {
        $data = $request->validate(['event_id' => 'required|exists:events,id']);
        $event = Event::find($data['event_id']);

        if ($user->id === $event->user_id) {
            return redirect()->back()->with('error', 'Der Owner kann nicht entfernt werden.');
        }

        $event->users()->detach($user->id);

        return redirect()->back()->with('success', "{$user->name} wurde entfernt.");
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
            'email' => 'required|email|exists:users,email',
            'event_id' => 'required|exists:events,id',
        ]);

        $user = User::where('email', $data['email'])->first();
        $event = Event::find($data['event_id']);

        $event->users()->syncWithoutDetaching([$user->id]);

        return redirect()->back()->with('success', "{$user->name} wurde zum Event hinzugefügt.");
    }
}
