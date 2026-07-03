<?php

namespace App\Observers;

use App\Models\Guest;
use App\Models\PhotoGameAssignment;

/**
 * Cascades a Guest deletion to every piece of personal data the guest owns.
 *
 * Runs BEFORE the row is gone so the child records still resolve. We loop
 * through photos and photo-game submissions with Eloquent deletes (not raw
 * SQL) so {@see PhotoObserver} fires for each and the object-storage blob is
 * removed. Foreign-key cascade would bypass the observer and leak files.
 *
 * A solo invitation token belongs to the guest and is deleted here. Group
 * tokens belong to the group as a whole and are left alone — deleting a
 * single family member must not lock the rest out of the app.
 */
class GuestObserver
{
    public function deleting(Guest $guest): void
    {
        // Photos owned by the guest — each fires PhotoObserver::deleting for the S3 blob.
        $guest->photos()->get()->each->delete();

        // Photo-game submissions may reference photos we haven't caught above
        // (e.g. re-submissions where the older Photo was not owned by the
        // guest anymore). Delete the referenced photo per assignment so the
        // storage object goes with it, then the assignment row.
        PhotoGameAssignment::with('photo')
            ->where('guest_id', $guest->id)
            ->get()
            ->each(function (PhotoGameAssignment $assignment) {
                $assignment->photo?->delete();
                $assignment->delete();
            });

        $guest->drinkLogs()->delete();
        $guest->foodSpecials()->detach();

        // Solo invitation token belongs to this guest — group tokens belong
        // to the group and must survive.
        if ($guest->invitationToken && $guest->invitationToken->guest_id === $guest->id) {
            $guest->invitationToken->delete();
        }

        // Any lingering Sanctum tokens.
        $guest->tokens()->delete();
    }
}
