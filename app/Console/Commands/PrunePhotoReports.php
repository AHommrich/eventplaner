<?php

namespace App\Console\Commands;

use App\Models\PhotoReport;
use Illuminate\Console\Command;

/**
 * Removes photo_reports rows whose associated event is more than
 * `config('retention.photo_reports_after_event_days')` days in the past.
 * The moderation trail is only useful for a limited window after the event —
 * after that we drop it to shrink our data footprint (GDPR Art. 5 (1) (e)).
 *
 * photo_hides and guest_content_hides do not need their own pruner: their FKs
 * cascade when the guest or photo row goes away.
 */
class PrunePhotoReports extends Command
{
    protected $signature = 'app:prune-photo-reports
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete photo_reports beyond the retention window.';

    public function handle(): int
    {
        $days = (int) config('retention.photo_reports_after_event_days');
        $cutoff = now()->subDays($days)->toDateString();

        $query = PhotoReport::query()
            ->whereHas('event', fn ($e) => $e->whereDate('date', '<', $cutoff));

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} photo report(s) past the {$days}-day window.");

            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Deleted {$count} photo report(s).");

        return self::SUCCESS;
    }
}
