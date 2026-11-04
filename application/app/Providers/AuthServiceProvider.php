<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Passport::tokensExpireIn(
            now()->addHours(config('auth.passport.tokens_expire_in_hours'))
        );

        Passport::refreshTokensExpireIn(
            now()->addHours(config('auth.passport.refresh_tokens_expire_in_hours'))
        );

        Passport::personalAccessTokensExpireIn(
            now()->addHours(config('auth.passport.personal_tokens_expire_in_hours'))
        );
    }
}
