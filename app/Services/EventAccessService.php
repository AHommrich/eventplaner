<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transactional writes for event membership + tiers.
 *
 * Authorization (who may assign/remove which tier) is decided by
 * {@see \App\Policies\EventPolicy} at the call site. This service owns the
 * *data invariant* — an event must never drop below one owner — and enforces it
 * transactionally with a row lock, so two concurrent "remove the other owner"
 * requests can't both read count=2 and race the event down to zero owners.
 *
 * The primary owner (events.user_id) is not a pivot row and is never touched
 * here; only pivot members (event_manager / event_admin / co-owners) are.
 */
class EventAccessService
{
    /**
     * Attach a new pivot member or change an existing member's tier.
     *
     * @param  'event_manager'|'event_admin'|'owner'  $role
     */
    public function setMemberRole(Event $event, User $target, string $role): void
    {
        DB::transaction(function () use ($event, $target, $role) {
            // Lock the event row so owner-count checks are consistent under concurrency.
            Event::whereKey($event->id)->lockForUpdate()->first();

            $currentRole = $event->users()
                ->where('users.id', $target->id)
                ->lockForUpdate()
                ->value('event_user.role');

            // Demoting an existing pivot owner must not drop the event below one owner.
            if ($currentRole === 'owner' && $role !== 'owner') {
                $this->assertNotLastOwner($event, $target);
            }

            $event->users()->syncWithoutDetaching([$target->id => ['role' => $role]]);

            // Assigned notes may only point at an active event_manager. If the
            // target leaves that tier (e.g. promoted to admin/owner), detach their
            // assignments so nothing stays booked on a non-manager.
            if ($role !== 'event_manager') {
                $this->detachAssignedNotes($event, $target);
            }

            // User tokens are not scoped to one event. A role change therefore
            // revokes every management session; the user can log in again and
            // receives only their newly authorized tier on the next request.
            if ($currentRole !== null && $currentRole !== $role) {
                $target->revokeApiTokens();
            }
        });
    }

    /** Remove a pivot member, guarding the last-owner invariant. */
    public function removeMember(Event $event, User $target): void
    {
        DB::transaction(function () use ($event, $target) {
            Event::whereKey($event->id)->lockForUpdate()->first();

            $currentRole = $event->users()
                ->where('users.id', $target->id)
                ->lockForUpdate()
                ->value('event_user.role');

            if ($currentRole === 'owner') {
                $this->assertNotLastOwner($event, $target);
            }

            $event->users()->detach($target->id);
            $this->detachAssignedNotes($event, $target);
            $target->revokeApiTokens();
        });
    }

    /**
     * Revert the target's assigned notes for this event to plain unassigned notes
     * (assignee_user_id = null) so nothing stays booked on someone who is no longer
     * an active event_manager. The assigning owner/admin keeps the item and can
     * re-assign it. Runs inside the caller's transaction.
     */
    private function detachAssignedNotes(Event $event, User $target): void
    {
        Note::where('event_id', $event->id)
            ->where('assignee_user_id', $target->id)
            ->update(['assignee_user_id' => null]);
    }

    /**
     * Abort if removing/demoting $target would leave the event with zero owners.
     * The primary owner (events.user_id) always counts, so this only bites when
     * the primary is somehow absent — but it keeps the invariant explicit.
     */
    private function assertNotLastOwner(Event $event, User $target): void
    {
        $remainingOwners = $event->owners()
            ->reject(fn (User $u) => $u->id === $target->id)
            ->count();

        if ($remainingOwners < 1) {
            throw ValidationException::withMessages([
                'role' => 'Der letzte verbleibende Owner kann nicht entfernt oder herabgestuft werden.',
            ]);
        }
    }
}
