<?php

namespace App\Console\Commands;

use App\Models\InvitationToken;
use Illuminate\Console\Command;

/**
 * Removes invitation tokens whose owning event is more than
 * `config('retention.invitation_tokens_after_event_days')` days in the past.
 * Once the wedding has happened (plus a small grace buffer for late additions)
 * the QR-tokens have no business purpose and must not linger as live credentials.
 */
class PruneInvitationTokens extends Command
{
    protected $signature = 'app:prune-invitation-tokens
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete invitation tokens whose event date is past the configured retention window.';

    public function handle(): int
    {
        $days = (int) config('retention.invitation_tokens_after_event_days');
        $cutoff = now()->subDays($days)->toDateString();

        $query = InvitationToken::query()->where(function ($q) use ($cutoff) {
            $q->whereHas('group.event', fn ($e) => $e->whereDate('date', '<', $cutoff))
                ->orWhereHas('guest.event', fn ($e) => $e->whereDate('date', '<', $cutoff));
        });

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} invitation token(s) older than {$days} days past event date.");

            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Deleted {$count} invitation token(s).");

        return self::SUCCESS;
    }
}
