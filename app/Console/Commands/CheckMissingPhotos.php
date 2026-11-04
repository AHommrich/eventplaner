<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Consistency check in the other direction of photos:cleanup-orphans: finds photo
 * rows whose blob is gone from the bucket (they render as broken tiles in the web
 * gallery and the app). Scheduled weekly in report-only mode, so drift reaches
 * GlitchTip before guests notice it.
 *
 * `--delete` removes the affected rows (Eloquent delete, so photo-game assignments
 * are detached via their FK and reports/hides cascade). Rows whose URL points at
 * another host (e.g. picsum demo data) have no storage key and are skipped.
 */
class CheckMissingPhotos extends Command
{
    protected $signature = 'photos:check-missing
                            {--delete : Delete photo rows whose blob is missing}';

    protected $description = 'Report (or delete) photo rows whose object-storage blob no longer exists.';

    public function handle(): int
    {
        $disk = Storage::disk('s3');

        [$checkable, $skipped] = Photo::query()->orderBy('id')->get()
            ->partition(fn (Photo $p) => $p->storageKey() !== null);

        // Never skip silently: a row that cannot be mapped to this bucket is either
        // external (demo data) or a misconfigured AWS_URL — both worth seeing.
        if ($skipped->isNotEmpty()) {
            $this->warn("Skipped {$skipped->count()} row(s) not stored on this bucket: #".$skipped->pluck('id')->implode(', #'));
        }

        $missing = $checkable->filter(fn (Photo $p) => ! $disk->exists($p->storageKey()));

        foreach ($missing as $photo) {
            $this->line("  - #{$photo->id} event {$photo->event_id} album {$photo->album_id} {$photo->created_at} {$photo->storageKey()}");
        }

        if ($missing->isEmpty()) {
            $this->info('All photo rows have their blob.');

            return self::SUCCESS;
        }

        if ($this->option('delete')) {
            $missing->each->delete();
            $this->info("Deleted {$missing->count()} photo row(s) with a missing blob.");

            return self::SUCCESS;
        }

        $this->error("{$missing->count()} photo row(s) point at a missing blob.");
        report(new RuntimeException("photos:check-missing: {$missing->count()} photo row(s) point at a missing blob."));

        return self::FAILURE;
    }
}
