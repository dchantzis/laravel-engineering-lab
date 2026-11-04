<?php

namespace App\Http\Middleware;

use App\Services\LogSystem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpTrace
{
    const string IP_TRACE_ID_SLUG = 'ip_trace_id';

    public function handle(Request $request, Closure $next): Response
    {
        $this->addIpTraceEntry($request);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->has(IpTrace::IP_TRACE_ID_SLUG)) {
            return;
        }

        $this->addIpTraceEntry($request);
    }

    private function addIpTraceEntry(Request $request): void
    {
        $ipTraceEntry = LogSystem::ipTrace()->add(
            $request->ip(),
            $request->method(),
            $request->getUri(),
            $request->all(),
            $this->getSafeRequestBody($request),
            $request->header(),
            $request->getHost()
        );

        $request->attributes->set(
            self::IP_TRACE_ID_SLUG,
            $ipTraceEntry->id
        );
    }

    private function getSafeRequestBody(Request $request): string|array
    {
        if ($request->isJson()) {
            return $request->json()->all();
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return [];
        }

        if (str_contains(
            strtolower($request->header('Content-Type', '')),
            'application/x-www-form-urlencoded'
        )) {
            return $request->request->all();
        }

        return '[Request body omitted: unsupported content type]';
    }
}
