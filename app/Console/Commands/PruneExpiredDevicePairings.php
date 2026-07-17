<?php

namespace App\Console\Commands;

use App\Models\DevicePairing;
use Illuminate\Console\Command;

class PruneExpiredDevicePairings extends Command
{
    protected $signature = 'app:prune-device-pairings
                            {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete expired, never-redeemed management pairing challenges.';

    public function handle(): int
    {
        $graceHours = (int) config('retention.expired_device_pairings_hours', 24);
        $query = DevicePairing::query()
            ->whereNull('redeemed_at')
            ->whereNull('personal_access_token_id')
            ->where('expires_at', '<=', now()->subHours($graceHours));
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} expired pairing challenge(s) after the {$graceHours}-hour grace period.");

            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Deleted {$count} expired pairing challenge(s).");

        return self::SUCCESS;
    }
}
