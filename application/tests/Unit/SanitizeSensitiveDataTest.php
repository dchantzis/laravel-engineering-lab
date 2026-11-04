<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanitizeSensitiveDataTest extends TestCase
{
    #[Test]
    public function it_redacts_sensitive_fields_recursively(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'secret123',
            'nested' => [
                'access_token' => 'abc123',
                'name' => 'Test User',
            ],
        ];

        $expected = [
            'email' => 'test@example.com',
            'password' => '********',
            'nested' => [
                'access_token' => '********',
                'name' => 'Test User',
            ],
        ];

        $this->assertSame(
            $expected,
            sanitizeSensitiveData($data, ['password', 'access_token'])
        );
    }

    #[Test]
    public function it_redacts_sensitive_headers_case_insensitively(): void
    {
        $headers = [
            'Authorization' => ['Bearer secret-token'],
            'Cookie' => ['session=secret'],
            'Content-Type' => ['application/json'],
            'X-API-KEY' => ['secret-key'],
        ];

        $expected = [
            'Authorization' => '********',
            'Cookie' => '********',
            'Content-Type' => ['application/json'],
            'X-API-KEY' => '********',
        ];

        $this->assertSame(
            $expected,
            sanitizeSensitiveData(
                $headers,
                ['authorization', 'cookie', 'x-api-key']
            )
        );
    }

    #[Test]
    public function it_decodes_and_redacts_json_request_bodies(): void
    {
        $body = '{"email":"test@example.com","password":"secret123","nested":{"access_token":"abc123"}}';

        $expected = [
            'email' => 'test@example.com',
            'password' => '********',
            'nested' => [
                'access_token' => '********',
            ],
        ];

        $this->assertSame(
            $expected,
            sanitizeSensitiveData($body, ['password', 'access_token'])
        );
    }

    #[Test]
    public function it_redacts_url_encoded_request_bodies(): void
    {
        $body = 'username=test&password=secret123';

        $expected = [
            'username' => 'test',
            'password' => '********',
        ];

        $this->assertSame(
            $expected,
            sanitizeSensitiveData($body, ['password'])
        );
    }

    #[Test]
    public function it_preserves_non_sensitive_fields(): void
    {
        $data = [
            'username' => 'test',
            'email' => 'test@example.com',
            'active' => true,
            'roles' => ['admin', 'editor'],
        ];

        $this->assertSame(
            $data,
            sanitizeSensitiveData($data, ['password', 'access_token'])
        );
    }

    #[Test]
    public function it_preserves_non_json_plain_text(): void
    {
        $value = 'This is a plain text message.';

        $this->assertSame(
            $value,
            sanitizeSensitiveData($value, ['password'])
        );
    }
}
