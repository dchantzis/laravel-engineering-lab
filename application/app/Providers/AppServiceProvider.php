<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Events\LogMonologEvent;
use App\Listeners\LogMonologEventListener;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use App\Enums\RateLimiterName;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\OauthClientRateLimitResolver;

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

        $this->bootRateLimiting();

        Event::listen(
            LogMonologEvent::class,
            LogMonologEventListener::class
        );
    }

    private function bootRateLimiting(): void
    {
        RateLimiter::for(
            RateLimiterName::WEB_RATE_LIMIT->value,
            function (Request $request): Limit {
                if (!config('rate-limiting.enabled')) {
                    return Limit::none();
                }

                return Limit::perMinute(
                    config('rate-limiting.web.default')
                )->by('web:' . ($request->user()?->getAuthIdentifier() ?? $request->ip()));
            }
        );

        RateLimiter::for(
            RateLimiterName::API_DEFAULT->value,
            function (Request $request): Limit {
                if (!config('rate-limiting.enabled')) {
                    return Limit::none();
                }

                return Limit::perMinute(
                    config('rate-limiting.api.default')
                )->by('api:' . ($request->user()?->getAuthIdentifier() ?? $request->ip()));
            }
        );

        RateLimiter::for(
            RateLimiterName::API_ADMIN->value,
            function (Request $request): Limit {
                if (! config('rate-limiting.enabled')) {
                    return Limit::none();
                }

                $admin = $request->user('api');

                if (! $admin) {
                    return Limit::none();
                }

                return Limit::perMinute(
                    config('rate-limiting.api.admin')
                )->by('api:admin:user:'.$admin->getAuthIdentifier());
            }
        );

        RateLimiter::for(
            RateLimiterName::API_CLIENT_CREDENTIALS->value,
            function (Request $request): Limit {
                if (! config('rate-limiting.enabled')) {
                    return Limit::none();
                }

                $clientId = getOauthClientId($request);

                $requestsPerMinute = app(OauthClientRateLimitResolver::class)
                    ->resolve($clientId);

                return Limit::perMinute($requestsPerMinute)
                    ->by('api:client:'.$clientId);
            }
        );
    }
}
