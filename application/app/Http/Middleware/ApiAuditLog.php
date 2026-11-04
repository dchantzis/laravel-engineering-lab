<?php

namespace App\Http\Middleware;

use App\Enums\ApiAuditLogType;
use App\Services\LogSystem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        LogSystem::api()->add(
            content: request()->all(),
            requestUuid: $request->attributes->get(GenerateApiRequestUuid::API_REQUEST_UUID_SLUG) ?: '',
            type: ApiAuditLogType::REQUEST,
            meta: [
                'headers' => request()->headers,
                'path' => request()->path(),
                'parameters' => request()->all(),
            ],
            ipTraceId: $request->attributes->get(IpTrace::IP_TRACE_ID_SLUG),
            oauthClientId: getOauthClientId($request),
            userId: auth()->user()?->id,
            resource: request()->path()
        );

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // The kernel terminates route middleware even when earlier middleware rejected the request.
        if (! $request->attributes->get(GenerateApiRequestUuid::API_REQUEST_UUID_SLUG)) {
            return;
        }

        LogSystem::api()->add(
            content: $response->getContent(),
            requestUuid: $request->attributes->get(GenerateApiRequestUuid::API_REQUEST_UUID_SLUG) ?: '',
            type: ApiAuditLogType::RESPONSE,
            httpStatusCode: $response->getStatusCode(),
            ipTraceId: $request->attributes->get(IpTrace::IP_TRACE_ID_SLUG),
            oauthClientId: getOauthClientId($request),
            userId: auth()->user()?->id,
            resource: request()->path()
        );
    }
}
