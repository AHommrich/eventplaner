<?php

namespace App\Observers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the R2 blob whenever a Photo row is deleted, regardless of the entry point
 * (single delete via the admin UI, batch delete, cascade from event/user deletion).
 *
 * Caveat: only Eloquent-driven deletes fire model events. Raw SQL deletes
 * (DB::table('photos')->delete(...)) bypass this observer — don't go that route.
 */
class PhotoObserver
{
    public function deleting(Photo $photo): void
    {
        if (! $photo->r2_key) {
            return;
        }

        // The configured disk is named "s3" but points at Cloudflare R2 via
        // AWS_ENDPOINT — see config/filesystems.php.
        Storage::disk('s3')->delete($photo->r2_key);
    }
}
