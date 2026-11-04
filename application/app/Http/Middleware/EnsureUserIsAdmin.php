<?php

namespace App\Http\Middleware;

use App\Services\AdminAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class EnsureUserIsAdmin
{
    public function __construct(
        private AdminAuthorizationService $adminAuthorizationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if (!$this->adminAuthorizationService->isAdmin($request->user('api'))) {
            abort(
                403,
                'Administrator access required'
            );
        }

        return $next($request);
    }
}
