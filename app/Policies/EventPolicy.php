<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Authorization for per-event tiers (the project's first policy).
 *
 * Tiers, low → high: event_manager → event_admin → owner → superadmin.
 * Ownership = primary owner (events.user_id) OR a pivot 'owner' co-owner
 * ({@see Event::isOwnedBy()}); superadmin = global users.role='admin'.
 *
 * The coarse abilities (view/manage/administer/manageAccess) are short-circuited
 * for superadmins in {@see before()}. The fine-grained, target-aware decisions
 * (changeAccess/removeMember/grantOwner/transferOwnership/deleteEvent) are NOT
 * blanket-granted there — they check their own rules so a superadmin can never,
 * e.g., be handed the global admin tier through an access change.
 */
class EventPolicy
{
    /**
     * Superadmins bypass the coarse gates only. Returning null lets the
     * fine-grained abilities run their own checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() && in_array($ability, ['view', 'manage', 'administer', 'manageAccess'], true)) {
            return true;
        }

        return null;
    }

    /** Any tier on the event may view it. */
    public function view(User $user, Event $event): bool
    {
        return $user->roleOn($event) !== null;
    }

    /** Manager-level actions: event_manager ∪ event_admin ∪ owner (∪ superadmin). */
    public function manage(User $user, Event $event): bool
    {
        return $user->canManage($event);
    }

    /** Administer-level actions: event_admin ∪ owner (∪ superadmin). */
    public function administer(User $user, Event $event): bool
    {
        return $user->canAdminister($event);
    }

    /**
     * Coarse "may open the access screen" gate: owner ∪ event_admin (∪ superadmin).
     * The actual add/change/remove decision needs target context — see
     * {@see changeAccess()} / {@see removeMember()}.
     */
    public function manageAccess(User $user, Event $event): bool
    {
        return in_array($user->roleOn($event), ['event_admin', 'owner', 'superadmin'], true);
    }

    /**
     * May create/manage owner-assigned notes for a manager: event_admin ∪ owner
     * (∪ superadmin). "Admin" here is the event_admin tier, not the global one —
     * event_admins may assign tasks to managers. The assignee-validity check
     * (target must be an active event_manager) lives at the write site.
     */
    public function assignNote(User $user, Event $event): bool
    {
        return $user->canAdminister($event);
    }

    /**
     * Central checkpoint for granting/changing a member's tier.
     *
     * - actor must pass manageAccess;
     * - event_admin may only assign event_manager (never event_admin or owner);
     * - assigning event_admin or owner requires owner ∪ superadmin;
     * - the global users.role='admin' tier is never assignable here.
     */
    public function changeAccess(User $actor, Event $event, ?User $target, string $newRole): bool
    {
        if (! $this->manageAccess($actor, $event)) {
            return false;
        }

        if (! in_array($newRole, ['event_manager', 'event_admin', 'owner'], true)) {
            return false;
        }

        if ($this->isOwnerTier($actor, $event)) {
            return true;
        }

        // event_admin actor: managers only.
        return $newRole === 'event_manager';
    }

    /**
     * Central checkpoint for removing a member — decided by the target's current tier.
     *
     * - actor must pass manageAccess;
     * - event_admin may only remove event_managers;
     * - removing an owner or event_admin requires owner ∪ superadmin.
     *
     * Last-owner protection is enforced transactionally at the write site, not here.
     */
    public function removeMember(User $actor, Event $event, User $target): bool
    {
        if (! $this->manageAccess($actor, $event)) {
            return false;
        }

        if ($this->isOwnerTier($actor, $event)) {
            return true;
        }

        // event_admin actor: managers only.
        return $target->roleOn($event) === 'event_manager';
    }

    /** Make someone a co-owner: owner ∪ superadmin only. */
    public function grantOwner(User $user, Event $event): bool
    {
        return $this->isOwnerTier($user, $event);
    }

    /** Swap the primary owner: owner ∪ superadmin only. Last-owner-safe at the write site. */
    public function transferOwnership(User $user, Event $event): bool
    {
        return $this->isOwnerTier($user, $event);
    }

    /**
     * Delete the whole event: owner ∪ superadmin only.
     *
     * Deliberately NOT subject to last-owner protection — deleting the event is a
     * legitimate sole-owner action that cascades everything.
     */
    public function deleteEvent(User $user, Event $event): bool
    {
        return $this->isOwnerTier($user, $event);
    }

    /** Owner tier = primary/pivot owner or superadmin. */
    private function isOwnerTier(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->isOwnedBy($user);
    }
}
