<?php

namespace App\Console\Commands;

use App\Models\Guest;
use Illuminate\Console\Command;

/**
 * Removes guest rows that declined the invitation, have no app access, and whose
 * event is more than `config('retention.declined_guests_after_event_days')` days
 * in the past. Hosts may still want the list briefly after the event (thank-you
 * cards) so the default window is generous (~6 months).
 */
class PruneDeclinedGuests extends Command
{
    protected $signature = 'app:prune-declined-guests
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete declined-and-orphaned guests beyond the retention window.';

    public function handle(): int
    {
        $days = (int) config('retention.declined_guests_after_event_days');
        $cutoff = now()->subDays($days)->toDateString();

        $query = Guest::query()
            ->where('rsvp_status', 'declined')
            ->where('app_access', false)
            ->whereHas('event', fn ($e) => $e->whereDate('date', '<', $cutoff));

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} declined guest(s) past the {$days}-day window.");

            return self::SUCCESS;
        }

        $query->get()->each(fn (Guest $g) => $g->delete());
        $this->info("Deleted {$count} declined guest(s).");

        return self::SUCCESS;
    }
}
