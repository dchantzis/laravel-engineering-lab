<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcceptJsonRequest
{
    /**
     * @throws Exception
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson()) {
            throw new ApiException('Request Header Accept must be json', Response::HTTP_NOT_ACCEPTABLE);
        }

        return $next($request);
    }
}
