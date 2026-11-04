<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Events\LogMonologEvent;
use App\Listeners\LogMonologEventListener;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;

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
        Passport::authorizationView('auth.oauth-authorize');

        Event::listen(
            LogMonologEvent::class,
            LogMonologEventListener::class
        );
    }
}
