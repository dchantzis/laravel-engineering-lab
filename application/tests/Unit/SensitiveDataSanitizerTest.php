<?php

namespace Tests\Unit;

use App\Services\Security\SensitiveDataSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SensitiveDataSanitizerTest extends TestCase
{
    private SensitiveDataSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new SensitiveDataSanitizer();

        config()->set('engineering.sensitive_data.enabled', true);
    }

    #[Test]
    public function it_sanitizes_nested_sensitive_fields(): void
    {
        $data = [
            'username' => 'john',
            'password' => 'secret123',
            'nested' => [
                'access_token' => 'abc123',
                'email' => 'john@example.com',
            ],
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertSame('********', $result['password']);
        $this->assertSame('********', $result['nested']['access_token']);
        $this->assertSame('john', $result['username']);
        $this->assertSame('john@example.com', $result['nested']['email']);
    }

    #[Test]
    public function it_sanitizes_sensitive_headers(): void
    {
        $headers = [
            'Authorization' => ['Bearer secret-token'],
            'Cookie' => ['session=secret'],
            'X-API-KEY' => ['secret-key'],
            'Content-Type' => ['application/json'],
        ];

        $result = $this->sanitizer->sanitize($headers);

        $this->assertSame('********', $result['Authorization']);
        $this->assertSame('********', $result['Cookie']);
        $this->assertSame('********', $result['X-API-KEY']);
        $this->assertSame(['application/json'], $result['Content-Type']);
    }

    #[Test]
    public function it_sanitizes_json_while_preserving_json_format(): void
    {
        $json = '{"username":"john","password":"secret123"}';

        $result = $this->sanitizer->sanitizeJson($json);

        $this->assertIsString($result);

        $this->assertSame(
            [
                'username' => 'john',
                'password' => '********',
            ],
            json_decode($result, true)
        );
    }

    #[Test]
    public function it_sanitizes_sensitive_url_parameters(): void
    {
        $url = 'https://example.com/api/orders?token=secret123&page=2';

        $result = $this->sanitizer->sanitizeUrl($url);

        $this->assertStringNotContainsString('secret123', $result);
        $this->assertStringContainsString('token=%2A%2A%2A%2A%2A%2A%2A%2A', $result);
        $this->assertStringContainsString('page=2', $result);
    }

    #[Test]
    public function it_preserves_non_sensitive_data(): void
    {
        $data = [
            'username' => 'john',
            'active' => true,
            'roles' => ['admin', 'editor'],
        ];

        $this->assertSame($data, $this->sanitizer->sanitize($data));
    }

    #[Test]
    public function it_respects_disabled_sanitization(): void
    {
        config()->set('engineering.sensitive_data.enabled', false);

        $data = ['password' => 'secret123'];

        if (isEnvironmentProduction()) {
            $this->assertSame(
                ['password' => '********'],
                $this->sanitizer->sanitize($data)
            );
        } else {
            $this->assertSame(
                $data,
                $this->sanitizer->sanitize($data)
            );
        }
    }

    #[Test]
    public function it_does_not_mutate_original_input(): void
    {
        $data = ['password' => 'secret123'];

        $this->sanitizer->sanitize($data);

        $this->assertSame('secret123', $data['password']);
    }

    #[Test]
    public function it_supports_custom_sensitive_fields_recursively(): void
    {
        $data = [
            'custom_secret' => 'secret123',
            'nested' => [
                'custom_secret' => 'another-secret',
            ],
        ];

        $result = $this->sanitizer->sanitize(
            $data,
            ['custom_secret']
        );

        $this->assertSame('********', $result['custom_secret']);
        $this->assertSame('********', $result['nested']['custom_secret']);
    }

    #[Test]
    public function it_sanitizes_form_encoded_data(): void
    {
        $result = $this->sanitizer->sanitizeFormData(
            'username=john&password=secret123',
            ['password']
        );

        $this->assertSame([
            'username' => 'john',
            'password' => '********',
        ], $result);
    }

    #[Test]
    public function it_sanitizes_audit_content(): void
    {
        $content = [
            'username' => 'john',
            'password' => 'secret123',
        ];

        $this->assertSame(
            [
                'username' => 'john',
                'password' => '********',
            ],
            $this->sanitizer->sanitizeContent($content)
        );
    }

    #[Test]
    public function it_omits_unstructured_audit_content(): void
    {
        $content = 'Authentication failed: token=secret123';

        $this->assertSame(
            '[Content omitted: unsupported or unstructured format]',
            $this->sanitizer->sanitizeContent($content)
        );
    }

    #[Test]
    public function it_forces_sanitization_when_disabled(): void
    {
        config()->set('engineering.sensitive_data.enabled', false);

        $data = ['password' => 'secret123'];

        $this->assertSame(
            ['password' => '********'],
            $this->sanitizer->sanitize($data, force: true)
        );
    }

    #[Test]
    public function it_omits_data_exceeding_maximum_recursion_depth(): void
    {
        $data = ['password' => 'secret123'];

        for ($i = 0; $i < 12; $i++) {
            $data = ['nested' => $data];
        }

        $result = $this->sanitizer->sanitize($data);

        for ($i = 0; $i < 10; $i++) {
            $result = $result['nested'];
        }

        $this->assertSame(
            '[Content omitted: maximum depth exceeded]',
            $result
        );
    }
}
