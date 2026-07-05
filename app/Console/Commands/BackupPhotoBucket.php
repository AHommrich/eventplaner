<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Weekly snapshot of the photo + cover prefixes into an in-bucket snapshot
 * prefix.
 *
 * Threat model this actually covers:
 *   - Accidental deletion of individual files by an application bug
 *     (mis-firing observer, buggy cleanup command, wrong DELETE cascade).
 *   - "I deleted the wrong photo" cases the user notices within a few weeks.
 *
 * Threat model this does NOT cover (deliberately — see docs/RUNBOOK.md §3):
 *   - Bucket entirely deleted from the Hetzner panel.
 *   - Object Storage API credentials compromised — attacker has equal access
 *     to the snapshot prefix.
 *   - Regional Hetzner outage in Nürnberg.
 *
 * The heavier hitters (bucket-loss, credentials, region-loss) are follow-ups
 * tracked in docs/legal/sub-processors.md ("Photo backup bucket").
 *
 * Implementation uses S3 server-side CopyObject via Laravel's `Storage::copy`
 * — no download/re-upload traffic. Idempotent: reruns of the same day skip
 * files already snapshotted.
 */
class BackupPhotoBucket extends Command
{
    protected $signature = 'photos:backup-to-prefix
                            {--prefix=snapshots : Root prefix under which snapshots are stored}
                            {--keep=4 : Number of dated snapshots to retain}
                            {--dry-run : Report only, do not copy or delete}';

    protected $description = 'Snapshot photos/ + covers/ into an in-bucket snapshot prefix (accidental-delete protection).';

    /** Bucket prefixes that get snapshotted. */
    private const SOURCE_PREFIXES = ['photos', 'covers'];

    public function handle(): int
    {
        $rootPrefix = trim((string) $this->option('prefix'), '/');
        $timestamp = now()->format('Y-m-d');
        $snapshotDir = "{$rootPrefix}/{$timestamp}";
        $dryRun = (bool) $this->option('dry-run');

        $copied = $this->copySources($snapshotDir, $dryRun);
        $removed = $this->applyRetention($rootPrefix, (int) $this->option('keep'), $dryRun);

        $this->info(sprintf(
            '%s %d file(s) into "%s/", pruned %d expired snapshot(s).',
            $dryRun ? '[dry-run] would copy' : 'Copied',
            $copied,
            $snapshotDir,
            $removed,
        ));

        return self::SUCCESS;
    }

    private function copySources(string $snapshotDir, bool $dryRun): int
    {
        $disk = Storage::disk('s3');
        $copied = 0;

        foreach (self::SOURCE_PREFIXES as $prefix) {
            foreach ($disk->allFiles($prefix) as $key) {
                $target = "{$snapshotDir}/{$key}";

                if ($disk->exists($target)) {
                    continue;
                }

                if (! $dryRun) {
                    $disk->copy($key, $target);
                }
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Keep the N newest dated snapshot directories, delete the rest.
     * Directory names are `YYYY-MM-DD`, so lexicographic sort == chronological.
     */
    private function applyRetention(string $rootPrefix, int $keep, bool $dryRun): int
    {
        if ($keep < 1) {
            return 0;
        }

        $disk = Storage::disk('s3');
        $snapshotDirs = $disk->directories($rootPrefix);
        sort($snapshotDirs);

        $obsolete = array_slice($snapshotDirs, 0, max(0, count($snapshotDirs) - $keep));

        foreach ($obsolete as $dir) {
            if (! $dryRun) {
                $disk->deleteDirectory($dir);
            }
        }

        return count($obsolete);
    }
}
