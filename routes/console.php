<?php

use App\Jobs\FetchExpoPushReceipts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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
Schedule::command('app:prune-device-pairings')->daily()->at('04:05');
Schedule::command('queue:prune-failed --hours='.(24 * (int) config('retention.failed_jobs_days', 7)))
    ->daily()
    ->at('04:10');
Schedule::job(new FetchExpoPushReceipts)->everyFiveMinutes();

// Low-volume deployment: drain the database queue from the existing scheduler
// instead of requiring a second long-running process in the web container.
// withoutOverlapping(10): cap the overlap mutex at 10 minutes. The lock lives in
// the database cache store (cache_locks); if a run is ever killed mid-flight
// (deploy/RAM pressure), the default 24h lock would silently pause queue draining
// for a full day. A 10-minute expiry self-heals well before that hurts.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(10);

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

// Scheduler-Heartbeat: bestätigt bei JEDEM Lauf die Lebendigkeit des Schedulers an GlitchTip
// (Heartbeat-Monitor). Der Sinn: Stirbt schedule:run auf Prozessebene (OOM/Container-Kill, Deploy) —
// der Fall, den ein PHP-Exception-Tracker prinzipiell NICHT sehen kann, weil kein PHP mehr läuft —,
// bleibt der Ping aus und GlitchTip alarmiert wegen ausbleibendem Heartbeat. Kurzer Timeout, Fehler
// bewusst geschluckt: der Heartbeat darf den Scheduler niemals selbst ins Straucheln bringen. Ohne
// gesetzte SCHEDULER_HEARTBEAT_URL inert (Muster wie der Backup-Job oben).
Schedule::call(function () {
    try {
        // GlitchTip erwartet einen POST (leerer Body); ein GET beantwortet der Endpoint mit 405.
        Http::timeout(5)->post((string) env('SCHEDULER_HEARTBEAT_URL'));
    } catch (\Throwable) {
        // best effort — ein nicht erreichbarer Heartbeat-Endpoint darf den Lauf nicht scheitern lassen
    }
})->everyMinute()
    ->name('scheduler-heartbeat')
    ->when(fn () => filled(env('SCHEDULER_HEARTBEAT_URL')));
