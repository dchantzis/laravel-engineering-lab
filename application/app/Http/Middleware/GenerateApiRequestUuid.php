<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GenerateApiRequestUuid
{
    const string API_REQUEST_UUID_SLUG = 'api_request_uuid';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(
            self::API_REQUEST_UUID_SLUG,
            (string) Str::orderedUuid()
        );

        return $next($request);
    }
}
