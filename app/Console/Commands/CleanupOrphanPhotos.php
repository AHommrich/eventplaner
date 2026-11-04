<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Backstop sweep: lists every object under the photo prefix in the object-storage
 * bucket (Hetzner Object Storage since 2026-07-01) and deletes anything no photo
 * or event cover references anymore. The column name `r2_key` is historical and
 * now holds Hetzner object keys.
 *
 * The Eloquent-level observer handles the happy path; this command catches any
 * drift caused by past raw SQL deletes, failed observers, or aborted requests
 * mid-upload. Scheduled weekly via routes/console.php, after the snapshot backups.
 *
 * Safety (incident 2026-11, see docs/DECISIONS.md): staging and production shared
 * one bucket with separate databases, so each instance's sweep deleted the other's
 * photos. A destructive run therefore requires
 *   1. an owner marker in the bucket matching this instance's APP_URL
 *      (written once via `--claim`), and
 *   2. at most `--max-delete` orphans; anything above aborts without deleting.
 * Legacy rows without r2_key are protected via their URL (Photo::storageKey()).
 */
class CleanupOrphanPhotos extends Command
{
    public const OWNER_MARKER = '.eventplaner-owner';

    protected $signature = 'photos:cleanup-orphans
                            {--prefix=photos/ : Bucket prefix to walk}
                            {--max-delete=20 : Abort without deleting anything when more orphans than this are found}
                            {--claim : Mark the bucket as exclusively owned by this instance (APP_URL)}
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete object-storage blobs that no photo or event cover references anymore.';

    public function handle(): int
    {
        $disk = Storage::disk('s3');
        $instance = (string) config('app.url');
        $owner = $disk->exists(self::OWNER_MARKER) ? trim((string) $disk->get(self::OWNER_MARKER)) : null;

        if ($this->option('claim')) {
            if ($owner !== null && $owner !== $instance) {
                $this->error("Bucket is already claimed by {$owner}. Give this instance its own bucket instead.");

                return self::FAILURE;
            }

            $disk->put(self::OWNER_MARKER, $instance);
            $this->info("Bucket claimed for {$instance}.");

            return self::SUCCESS;
        }

        $prefix = (string) $this->option('prefix');
        $orphans = array_values(array_diff($disk->allFiles($prefix), $this->liveKeys()));

        if ($this->option('dry-run')) {
            $this->info('Bucket owner: '.($owner ?? 'unclaimed').' (this instance: '.$instance.')');
            $this->info('Would delete '.count($orphans).' orphan object(s) under "'.$prefix.'".');

            foreach ($orphans as $key) {
                $this->line('  - '.$key);
            }

            return self::SUCCESS;
        }

        if ($owner !== $instance) {
            return $this->refuse('Bucket owner is '.($owner ?? 'unclaimed').", not {$instance}. Refusing to delete; run --claim only if this instance owns the bucket exclusively.");
        }

        $max = (int) $this->option('max-delete');
        if (count($orphans) > $max) {
            return $this->refuse('Found '.count($orphans)." orphan(s), more than --max-delete={$max}. Refusing to delete; check with --dry-run first.");
        }

        foreach ($orphans as $key) {
            $disk->delete($key);
        }

        $this->info('Deleted '.count($orphans).' orphan object(s) under "'.$prefix.'".');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function liveKeys(): array
    {
        $photoKeys = Photo::query()->get(['id', 'url', 'r2_key'])->map(fn (Photo $p) => $p->storageKey());

        $coverKeys = Event::query()->get(['id', 'cover_image_url', 'cover_image_r2_key'])
            ->map(fn (Event $e) => $e->cover_image_r2_key ?: Photo::keyFromDiskUrl($e->cover_image_url));

        return $photoKeys->merge($coverKeys)->filter()->unique()->values()->all();
    }

    private function refuse(string $message): int
    {
        $this->error($message);
        // Scheduled runs are unattended — surface the refusal in GlitchTip.
        report(new RuntimeException('photos:cleanup-orphans: '.$message));

        return self::FAILURE;
    }
}
