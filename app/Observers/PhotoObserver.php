<?php

namespace App\Observers;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the object-storage blob whenever a Photo row is deleted, regardless of the
 * entry point (single delete via the admin UI, batch delete, cascade from event/user
 * deletion).
 *
 * The `r2_key` column name is historical (project was on Cloudflare R2 until
 * 2026-07-01, then migrated to Hetzner Object Storage); the value is now a Hetzner
 * object key.
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

        // The configured disk is named "s3" and points at Hetzner Object Storage
        // via AWS_ENDPOINT / AWS_BUCKET — see config/filesystems.php.
        Storage::disk('s3')->delete($photo->r2_key);
    }
}
