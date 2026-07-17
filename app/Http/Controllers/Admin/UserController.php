<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'is_approved', 'created_at']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'sometimes|required|in:admin,user',
            'is_approved' => 'sometimes|required|boolean',
        ]);
        if (! $request->hasAny(['role', 'is_approved'])) {
            throw ValidationException::withMessages(['user' => 'Mindestens eine Änderung ist erforderlich.']);
        }

        DB::transaction(function () use ($user, $data) {
            // Lock the complete admin set before the target so concurrent
            // demotions cannot both observe "one other admin" and remove both.
            if (($data['role'] ?? null) === 'user') {
                User::query()->where('role', 'admin')->lockForUpdate()->get(['id']);
            }

            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $roleChanged = array_key_exists('role', $data) && $data['role'] !== $target->role;
            if ($roleChanged && $target->isAdmin() && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Der letzte Administrator kann nicht herabgestuft werden.',
                ]);
            }

            $wasAuthorized = $target->isApproved();
            $target->update($data);

            if ($roleChanged || ($wasAuthorized && ! $target->isApproved())) {
                $target->revokeApiTokens();
            }
        });

        return redirect()->back()->with('success', 'User aktualisiert.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'Du kannst dich nicht selbst löschen.');
        }

        $error = DB::transaction(function () use ($user): ?string {
            // Same lock order as update(): serialize delete vs. demotion too.
            User::query()->where('role', 'admin')->lockForUpdate()->get(['id']);
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($target->isAdmin() && User::where('role', 'admin')->count() <= 1) {
                return 'Der letzte Administrator kann nicht gelöscht werden.';
            }

            // A user who owns an event must not be deleted: with the RESTRICT
            // FK this would fail at the DB anyway; ownership must move first.
            if ($target->ownedEvents()->exists()) {
                return 'Dieser Nutzer ist Eigentümer eines Events. Übertrage zuerst die Eigentümerschaft oder lösche das Event.';
            }

            $target->delete();

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->back()->with('success', 'User gelöscht.');
    }
}
