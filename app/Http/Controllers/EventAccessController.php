<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\EventAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Manage per-event membership + tiers.
 *
 * The coarse "may open this screen" gate is the `can_administer` route middleware
 * (owner ∪ event_admin ∪ superadmin). The fine-grained, target-aware decisions
 * (which tier the actor may assign/remove) run through {@see \App\Policies\EventPolicy}
 * (changeAccess / removeMember). Transactional writes + the last-owner invariant
 * live in {@see EventAccessService}. Frontend gating is cosmetic; this is authoritative.
 */
class EventAccessController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        if (! $event) {
            return redirect()->route('dashboard');
        }

        $user = auth()->user();
        $this->authorize('manageAccess', $event);

        $primaryOwner = $event->owner;

        $members = $event->users()
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'role' => $m->pivot->role,
            ]);

        return Inertia::render('Event/Access', [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'owner' => [
                'id' => $primaryOwner->id,
                'name' => $primaryOwner->name,
                'email' => $primaryOwner->email,
            ],
            'members' => $members,
            // Cosmetic capability flags for the UI; the server checkpoint is authoritative.
            'my_role' => $user->roleOn($event),
            'can_assign_admin' => $event->isOwnedBy($user) || $user->isAdmin(),
        ]);
    }

    public function invite(Request $request, EventAccessService $access)
    {
        $event = $this->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Kein aktives Event.');
        }

        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'nullable|in:event_manager,event_admin',
        ]);
        $role = $data['role'] ?? 'event_manager';

        $invitee = User::where('email', $data['email'])->first();

        if ($this->isPrimaryOwner($event, $invitee)) {
            return redirect()->back()->with('error', 'Der Owner ist bereits in diesem Event.');
        }

        if (! auth()->user()->can('changeAccess', [$event, $invitee, $role])) {
            abort(403);
        }

        $access->setMemberRole($event, $invitee, $role);

        return redirect()->back()->with('success', "{$invitee->name} wurde hinzugefügt.");
    }

    public function updateRole(Request $request, User $user, EventAccessService $access)
    {
        $event = $this->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Kein aktives Event.');
        }

        $data = $request->validate([
            'role' => 'required|in:event_manager,event_admin,owner',
        ]);

        if ($this->isPrimaryOwner($event, $user)) {
            return redirect()->back()->with('error', 'Die Rolle des Haupt-Owners kann hier nicht geändert werden.');
        }

        if (! $event->users()->where('users.id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'Diese Person ist kein Mitglied dieses Events.');
        }

        if (! auth()->user()->can('changeAccess', [$event, $user, $data['role']])) {
            abort(403);
        }

        $access->setMemberRole($event, $user, $data['role']);

        return redirect()->back()->with('success', "{$user->name} wurde aktualisiert.");
    }

    public function remove(Request $request, User $user, EventAccessService $access)
    {
        $event = $this->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Kein aktives Event.');
        }

        if ($this->isPrimaryOwner($event, $user)) {
            return redirect()->back()->with('error', 'Der Owner kann nicht entfernt werden.');
        }

        if (! $event->users()->where('users.id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'Diese Person ist kein Mitglied dieses Events.');
        }

        if (! auth()->user()->can('removeMember', [$event, $user])) {
            abort(403);
        }

        $access->removeMember($event, $user);

        return redirect()->back()->with('success', "{$user->name} wurde entfernt.");
    }

    private function isPrimaryOwner(Event $event, User $user): bool
    {
        return $event->user_id === $user->id;
    }
}
