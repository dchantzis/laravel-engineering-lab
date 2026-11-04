<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistOauthClientId
{
    const string OAUTH_CLIENT_ID_SLUG = 'oauth_client_id';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::OAUTH_CLIENT_ID_SLUG, getOauthClientId($request));

        return $next($request);
    }
}
