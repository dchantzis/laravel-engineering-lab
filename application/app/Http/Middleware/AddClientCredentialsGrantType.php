<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddClientCredentialsGrantType
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->merge(['grant_type' => 'client_credentials']);

        return $next($request);
    }
}
