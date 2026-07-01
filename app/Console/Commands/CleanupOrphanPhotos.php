<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backstop sweep: lists every object under the photo prefix in the R2 bucket
 * and deletes anything not referenced by a `photos.r2_key` row anymore.
 *
 * The Eloquent-level observer handles the happy path; this command catches any
 * drift caused by past raw SQL deletes, failed observers, or aborted requests
 * mid-upload. Scheduled weekly via bootstrap/app.php.
 */
class CleanupOrphanPhotos extends Command
{
    protected $signature = 'photos:cleanup-orphans
                            {--prefix=photos/ : Bucket prefix to walk}
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete R2 blobs whose photos.r2_key reference has gone away.';

    public function handle(): int
    {
        $prefix = (string) $this->option('prefix');

        $live = Photo::whereNotNull('r2_key')->pluck('r2_key')->all();
        $bucket = Storage::disk('s3')->allFiles($prefix);

        $orphans = array_values(array_diff($bucket, $live));

        if ($this->option('dry-run')) {
            $this->info('Would delete '.count($orphans).' orphan object(s) under "'.$prefix.'".');

            foreach ($orphans as $key) {
                $this->line('  - '.$key);
            }

            return self::SUCCESS;
        }

        foreach ($orphans as $key) {
            Storage::disk('s3')->delete($key);
        }

        $this->info('Deleted '.count($orphans).' orphan object(s) under "'.$prefix.'".');

        return self::SUCCESS;
    }
}
