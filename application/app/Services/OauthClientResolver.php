<?php

namespace App\Services;

use App\Http\Middleware\EnsureOauthClientIsResourceOwner;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Exceptions\AuthenticationException;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

readonly class OauthClientResolver
{
    public function __construct(
        private ResourceServer $server,
    ) {
    }

    public function resolve(Request $request): string
    {
        $attribute = EnsureOauthClientIsResourceOwner::OAUTH_CLIENT_ID_SLUG;

        $oauthClientId = $request->attributes->get($attribute);

        if (is_string($oauthClientId) && $oauthClientId !== '') {
            return $oauthClientId;
        }

        $psrRequest = (new PsrHttpFactory)->createRequest($request);

        try {
            $validatedRequest = $this->server
                ->validateAuthenticatedRequest($psrRequest);
        } catch (OAuthServerException) {
            throw new AuthenticationException;
        }

        $token = AccessToken::fromPsrRequest($validatedRequest);

        $oauthClientId = $token->oauth_client_id;

        if (! is_string($oauthClientId) || $oauthClientId === '') {
            throw new AuthenticationException;
        }

        $request->attributes->set($attribute, $oauthClientId);

        return $oauthClientId;
    }
}
