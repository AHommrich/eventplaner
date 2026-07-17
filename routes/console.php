<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled jobs.
 * Run via `php artisan schedule:run` once per minute (cron entry on the host)
 * or `php artisan schedule:work` in dev. See docs/gdpr/ for the rationale
 * behind each retention / cleanup job.
 */
Schedule::command('photos:cleanup-orphans')->weekly()->sundays()->at('03:00');
Schedule::command('app:prune-invitation-tokens')->weekly()->sundays()->at('03:15');
Schedule::command('app:prune-declined-guests')->weekly()->sundays()->at('03:30');
Schedule::command('guests:purge-expired-erasures')->daily()->at('03:45');
Schedule::command('app:prune-photo-reports')->weekly()->sundays()->at('03:50');
Schedule::command('app:prune-notes')->weekly()->sundays()->at('03:55');
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Accidental-delete protection: weekly snapshot of photos/ + covers/ into
// snapshots/YYYY-MM-DD/ inside the same bucket. See app/Console/Commands/BackupPhotoBucket.php
// for the scope + limits of this backup layer.
Schedule::command('photos:backup-to-prefix')->weekly()->sundays()->at('04:00');

// Cross-region backup: stream the same content into the Helsinki backup bucket.
// Longer retention (keep=8 ≈ two months). Protects against primary bucket loss,
// credential compromise, and a Nürnberg regional outage. Only runs when the
// AWS_BACKUP_* env vars are configured — until then the job is inert and the
// scheduler stays green (see .env.example + docs/RUNBOOK.md §3.2b).
Schedule::command('photos:backup-to-prefix --target=hel1 --keep=8')
    ->weekly()->sundays()->at('04:30')
    ->when(fn () => filled(env('AWS_BACKUP_BUCKET')));
