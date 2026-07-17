<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Photo;
use App\Observers\GuestObserver;
use App\Observers\PhotoObserver;
use App\Policies\EventPolicy;
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
    }
}
