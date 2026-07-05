<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Snapshot of the photo + cover prefixes into a dated backup directory.
 *
 * Two targets are supported via `--target`:
 *
 *   --target=in-bucket (default)
 *       Copies within the primary bucket into `snapshots/YYYY-MM-DD/…`. Uses
 *       S3 server-side CopyObject via Laravel's `Storage::copy` — no download.
 *       Protects against accidental deletion of individual objects only.
 *
 *   --target=hel1
 *       Streams into the Hetzner Helsinki backup bucket (disk `s3_backup`)
 *       under the same dated prefix. Additionally protects against primary
 *       bucket loss, primary credential compromise, and a Nürnberg regional
 *       outage — provided the backup disk uses a SEPARATE access key with
 *       PutObject/ListBucket rights on the backup bucket only.
 *
 * Both modes are idempotent (existing target objects are skipped) and honour
 * `--keep` for retention on the target disk. See docs/RUNBOOK.md §3.2 for the
 * full threat model per layer.
 */
class BackupPhotoBucket extends Command
{
    protected $signature = 'photos:backup-to-prefix
                            {--target=in-bucket : Where snapshots land — "in-bucket" (default) or "hel1"}
                            {--prefix=snapshots : Root prefix under which snapshots are stored}
                            {--keep=4 : Number of dated snapshots to retain on the target disk}
                            {--dry-run : Report only, do not copy or delete}';

    protected $description = 'Snapshot photos/ + covers/ into a dated backup prefix (in the same bucket, or into the Helsinki backup bucket).';

    private const SOURCE_DISK = 's3';

    /** Bucket prefixes that get snapshotted. */
    private const SOURCE_PREFIXES = ['photos', 'covers'];

    private const TARGET_IN_BUCKET = 'in-bucket';

    private const TARGET_HELSINKI = 'hel1';

    public function handle(): int
    {
        $targetOption = (string) $this->option('target');
        $targetDisk = $this->resolveTargetDisk($targetOption);

        if ($targetDisk === null) {
            $this->error("Unknown --target '{$targetOption}'. Use 'in-bucket' or 'hel1'.");

            return self::FAILURE;
        }

        $rootPrefix = trim((string) $this->option('prefix'), '/');
        $timestamp = now()->format('Y-m-d');
        $snapshotDir = "{$rootPrefix}/{$timestamp}";
        $dryRun = (bool) $this->option('dry-run');

        $copied = $this->copySources($targetDisk, $snapshotDir, $dryRun);
        $removed = $this->applyRetention($targetDisk, $rootPrefix, (int) $this->option('keep'), $dryRun);

        $this->info(sprintf(
            '%s %d file(s) into "%s/" on target "%s", pruned %d expired snapshot(s).',
            $dryRun ? '[dry-run] would copy' : 'Copied',
            $copied,
            $snapshotDir,
            $targetOption,
            $removed,
        ));

        return self::SUCCESS;
    }

    private function resolveTargetDisk(string $target): ?Filesystem
    {
        return match ($target) {
            self::TARGET_IN_BUCKET => Storage::disk(self::SOURCE_DISK),
            self::TARGET_HELSINKI => Storage::disk('s3_backup'),
            default => null,
        };
    }

    private function copySources(Filesystem $targetDisk, string $snapshotDir, bool $dryRun): int
    {
        $sourceDisk = Storage::disk(self::SOURCE_DISK);
        $sameDisk = $targetDisk === $sourceDisk;
        $copied = 0;

        foreach (self::SOURCE_PREFIXES as $prefix) {
            foreach ($sourceDisk->allFiles($prefix) as $key) {
                $target = "{$snapshotDir}/{$key}";

                if ($targetDisk->exists($target)) {
                    continue;
                }

                if (! $dryRun) {
                    if ($sameDisk) {
                        // Server-side CopyObject — no bytes cross the wire.
                        $sourceDisk->copy($key, $target);
                    } else {
                        // Cross-disk / cross-region: stream download → upload.
                        // Slower and traffic-billed, but the only portable path
                        // when source and destination are different S3 endpoints.
                        $stream = $sourceDisk->readStream($key);
                        if ($stream === null) {
                            continue;
                        }
                        $targetDisk->writeStream($target, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Keep the N newest dated snapshot directories on the target disk, delete the rest.
     * Directory names are `YYYY-MM-DD`, so lexicographic sort == chronological.
     */
    private function applyRetention(Filesystem $targetDisk, string $rootPrefix, int $keep, bool $dryRun): int
    {
        if ($keep < 1) {
            return 0;
        }

        $snapshotDirs = $targetDisk->directories($rootPrefix);
        sort($snapshotDirs);

        $obsolete = array_slice($snapshotDirs, 0, max(0, count($snapshotDirs) - $keep));

        foreach ($obsolete as $dir) {
            if (! $dryRun) {
                $targetDisk->deleteDirectory($dir);
            }
        }

        return count($obsolete);
    }
}
