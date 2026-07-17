<?php

namespace App\Console\Commands;

use App\Models\Note;
use Illuminate\Console\Command;

/**
 * Hard-purges organizer notes/todos beyond the retention window
 * (`config('retention.notes_after_delete_days')`):
 *
 *  1. soft-deleted notes whose `deleted_at` is older than the window, and
 *  2. every note (trashed or not) of an event whose date is more than the
 *     window in the past — the task list is no longer needed once the event is
 *     long over, mirroring `app:prune-declined-guests` / `app:prune-invitation-tokens`.
 *
 * Until purged, soft-deleted notes stay out of the UI but remain in the Art. 15
 * data export (with a `deleted_at` marker).
 */
class PruneNotes extends Command
{
    protected $signature = 'app:prune-notes
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Hard-delete notes/todos beyond the retention window.';

    public function handle(): int
    {
        $days = (int) config('retention.notes_after_delete_days');
        $deletedCutoff = now()->subDays($days);
        $eventCutoff = now()->subDays($days)->toDateString();

        // 1. Soft-deleted notes past the window.
        $trashed = Note::onlyTrashed()->where('deleted_at', '<', $deletedCutoff);

        // 2. All notes of long-past events (trashed or not).
        $pastEvent = Note::withTrashed()
            ->whereHas('event', fn ($e) => $e->whereDate('date', '<', $eventCutoff));

        $count = (clone $trashed)->count() + (clone $pastEvent)->count();

        if ($this->option('dry-run')) {
            $this->info("Would hard-delete {$count} note(s) past the {$days}-day window.");

            return self::SUCCESS;
        }

        $trashed->forceDelete();
        $pastEvent->forceDelete();

        $this->info("Hard-deleted {$count} note(s).");

        return self::SUCCESS;
    }
}
