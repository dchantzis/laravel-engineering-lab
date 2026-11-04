<?php

namespace Tests\Feature;

use App\Enums\DatabaseConnection;
use App\Models\IpTrace;
use Tests\TestCase;

class IpTraceLifecycleTest extends TestCase
{
    public function test_explicitly_traced_route_creates_exactly_one_ip_trace_when_authentication_is_rejected(): void
    {
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('a', 32)));

        $this->getJson('http://localhost/api/test')->assertUnauthorized();

        $this->assertSame(
            1,
            IpTrace::on(DatabaseConnection::APPLICATION)->count()
        );

        $trace = IpTrace::on(DatabaseConnection::APPLICATION)->first();

        $this->assertNotNull($trace);
        $this->assertSame('GET', $trace->request_method);
        $this->assertSame('localhost', $trace->host);
        $this->assertSame('http://localhost/api/test', $trace->route);
    }

    public function test_route_without_tracing_middleware_does_not_create_an_ip_trace(): void
    {
        $this->get('/')->assertOk();

        $this->assertDatabaseCount('ip_trace', 0, DatabaseConnection::APPLICATION);
    }
}
