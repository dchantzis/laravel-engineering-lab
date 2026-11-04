<?php

namespace Tests\Unit;

use App\Http\Middleware\IpTrace;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IpTraceMiddlewareTest extends TestCase
{
    private function getSafeRequestBody(Request $request): string|array
    {
        $method = new \ReflectionMethod(
            IpTrace::class,
            'getSafeRequestBody'
        );

        return $method->invoke(new IpTrace(), $request);
    }

    #[Test]
    public function it_extracts_json_request_bodies(): void
    {
        $request = Request::create(
            '/api/example',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"username":"test","password":"secret123"}'
        );

        $this->assertSame(
            [
                'username' => 'test',
                'password' => 'secret123',
            ],
            $this->getSafeRequestBody($request)
        );
    }

    #[Test]
    public function it_extracts_url_encoded_request_bodies(): void
    {
        $request = Request::create(
            '/api/example',
            'POST',
            [
                'username' => 'test',
                'password' => 'secret123',
            ],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(
            [
                'username' => 'test',
                'password' => 'secret123',
            ],
            $this->getSafeRequestBody($request)
        );
    }

    #[Test]
    public function it_omits_unsupported_request_bodies(): void
    {
        $request = Request::create(
            '/api/example',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            'password=secret123'
        );

        $this->assertSame(
            '[Request body omitted: unsupported content type]',
            $this->getSafeRequestBody($request)
        );
    }

    #[Test]
    public function it_omits_get_request_bodies(): void
    {
        $request = Request::create('/api/example', 'GET');

        $this->assertSame(
            [],
            $this->getSafeRequestBody($request)
        );
    }
}
