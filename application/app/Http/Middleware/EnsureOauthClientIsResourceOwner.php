<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use Symfony\Component\HttpFoundation\Response;

class EnsureOauthClientIsResourceOwner extends EnsureClientIsResourceOwner
{
    const string OAUTH_CLIENT_ID_SLUG = 'oauth_client_id';

    public function handle(
        Request $request,
        Closure $next,
        string ...$params
    ): Response {
        $token = $this->validateToken($request);

        $this->validate(
            $token,
            ...$params
        );

        if (! $token instanceof AccessToken) {
            throw new AuthenticationException;
        }

        $request->attributes->set(
            EnsureOauthClientIsResourceOwner::OAUTH_CLIENT_ID_SLUG,
            $token->oauth_client_id
        );

        return $next($request);
    }
}
