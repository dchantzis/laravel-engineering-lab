<?php

namespace Tests\Feature;

use App\Enums\DatabaseConnection;
use App\Http\Middleware\IpTrace;
use Illuminate\Http\Request;
use Tests\TestCase;

class IpTracePersistenceTest extends TestCase
{

    public function test_it_persists_an_ip_trace_and_redacts_sensitive_data(): void
    {
        $request = Request::create(
            '/api/example?source=portfolio',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer secret-token',
            ],
            json_encode([
                'username' => 'test',
                'password' => 'secret123',
            ])
        );

        $middleware = new IpTrace();

        $response = $middleware->handle(
            $request,
            fn () => response('OK')
        );

        $this->assertSame(200, $response->getStatusCode());

        $traceId = $request->attributes->get(IpTrace::IP_TRACE_ID_SLUG);

        $this->assertNotNull($traceId);

        $trace = \App\Models\IpTrace::find($traceId);

        $this->assertNotNull($trace);

        $this->assertSame('POST', $trace->request_method);

        $this->assertSame(
            '********',
            $trace->request_body->password
        );

        $this->assertSame(
            'test',
            $trace->request_body->username
        );

        $this->assertSame(
            '********',
            $trace->request_parameters->password
        );

        $this->assertSame(
            'portfolio',
            $trace->request_parameters->source
        );

        $this->assertSame(
            '********',
            $trace->request_header->authorization
        );
    }
}
