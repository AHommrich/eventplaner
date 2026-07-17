<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Global user administration (superadmin only): platform role + account deletion.
 *
 * Per-event access + tiers live entirely in {@see \App\Http\Controllers\EventAccessController}
 * (`/event/access`) since the P1 §8.1 split — a superadmin reaches any event's
 * access page via the event switcher. This screen no longer touches `event_user`.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Users', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']),
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

        // Never let the platform end up without a superadmin.
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return redirect()->back()->with('error', 'Der letzte Administrator kann nicht gelöscht werden.');
        }

        // A user who owns an event must not be deleted: with the RESTRICT FK
        // (see the 2026_07_16 migration) this would fail at the DB anyway, and
        // conceptually the event's ownership has to be transferred — or the
        // event deleted — first. Shared-event memberships (event_user) cascade
        // away automatically, so only ownership blocks deletion.
        if ($user->ownedEvents()->exists()) {
            return redirect()->back()->with('error', 'Dieser Nutzer ist Eigentümer eines Events. Übertrage zuerst die Eigentümerschaft oder lösche das Event.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User gelöscht.');
    }
}
