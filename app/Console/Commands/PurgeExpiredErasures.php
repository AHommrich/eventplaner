<?php

namespace App\Console\Commands;

use App\Models\Guest;
use Illuminate\Console\Command;

/**
 * Hard-deletes guests whose `scheduled_erasure_at` has passed. The 30-day
 * grace window during which the guest can revoke lives in
 * `retention.guest_erasure_grace_days`; this command runs after it.
 *
 * The heavy lifting (photos → S3, drink logs, invitation tokens, food
 * specials, Sanctum tokens) is done by GuestObserver::deleting.
 */
class PurgeExpiredErasures extends Command
{
    protected $signature = 'guests:purge-expired-erasures
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete guests whose Art. 17 erasure grace window has expired.';

    public function handle(): int
    {
        $query = Guest::query()
            ->whereNotNull('scheduled_erasure_at')
            ->where('scheduled_erasure_at', '<=', now());

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} guest(s) past the erasure grace window.");

            return self::SUCCESS;
        }

        $query->get()->each(fn (Guest $g) => $g->delete());
        $this->info("Deleted {$count} guest(s).");

        return self::SUCCESS;
    }
}
