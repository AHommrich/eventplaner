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
Schedule::command('sanctum:prune-expired --hours=24')->daily();
