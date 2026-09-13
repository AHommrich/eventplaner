<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Photo;
use App\Observers\GuestObserver;
use App\Observers\PhotoObserver;
use App\Policies\EventPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
            if (config('app.url')) {
                URL::forceRootUrl(config('app.url'));
            }
        }

        Gate::policy(Event::class, EventPolicy::class);

        Photo::observe(PhotoObserver::class);
        Guest::observe(GuestObserver::class);

        // Keep the scheduler's overlap mutex off the application database.
        // The once-per-minute queue drain uses withoutOverlapping(), whose lock
        // otherwise lives in `cache_locks` (CACHE_STORE=database). Its existence
        // check runs in schedule:run's filter phase, *outside* the command's
        // try/catch — so a transient DNS blip resolving the `mariadb` host
        // (SQLSTATE[HY000] [2002] getaddrinfo failed) throws unhandled and kills
        // the whole run, tripping Coolify's "scheduled task failed" alarm. A
        // file lock keeps the overlap protection but removes the DB/DNS dependency.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->useCache('file');
        });
    }
}
