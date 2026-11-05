<?php

namespace Tests\Feature;

use App\Enums\ApiAuditLogType;
use App\Enums\DatabaseConnection;
use App\Models\ApiAuditLog;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiAuditLogHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('engineering.api_audit_logs_enabled', true);
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('a', 32)));
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
    }

    public function test_authenticated_integration_request_is_audited(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $response = $this->getIntegrationResponse($client);

        $uuid = $response->json('data.api_request_uuid');
        $this->assertIsString($uuid);
        $this->assertNotSame('', $uuid);
        $request = ApiAuditLog::where('request_uuid', $uuid)
            ->where('type', ApiAuditLogType::REQUEST->value)->sole();
        $this->assertSame('api/test', $request->resource);
        $this->assertSame($client->id, $request->oauth_client_id);
        $this->assertNotNull($request->ip_trace_id);
        $this->assertNull($request->user_id);
        $this->assertSame('audit-test', $request->content->marker);
        $this->assertSame('api/test', $request->meta->path);
        $this->assertSame('audit-test', $request->meta->parameters->marker);
    }

    public function test_terminated_response_is_audited_with_status_and_content(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        // Laravel's MakesHttpRequests::call invokes kernel termination; do not terminate twice.
        $response = $this->getIntegrationResponse($client);

        $entry = ApiAuditLog::where('request_uuid', $response->json('data.api_request_uuid'))
            ->where('type', ApiAuditLogType::RESPONSE->value)->sole();
        $this->assertSame(200, $entry->http_status_code);
        $this->assertSame($response->getStatusCode(), $entry->http_status_code);
        $this->assertSame(json_decode($response->getContent(), true), json_decode(json_encode($entry->content), true));
        $this->assertSame('API routing works', $entry->content->data->message);
    }

    public function test_request_and_response_share_exactly_one_correlation_pair(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $response = $this->getIntegrationResponse($client);

        $uuid = $response->json('data.api_request_uuid');
        $this->assertIsString($uuid);
        $this->assertNotSame('', $uuid);
        $entries = ApiAuditLog::where('request_uuid', $uuid)->get();
        $this->assertCount(2, $entries);
        $request = $entries->where('type', ApiAuditLogType::REQUEST->value)->sole();
        $reply = $entries->where('type', ApiAuditLogType::RESPONSE->value)->sole();
        $this->assertSame('api/test', $request->resource);
        $this->assertSame($request->resource, $reply->resource);
        $this->assertNotNull($request->ip_trace_id);
        $this->assertSame($request->ip_trace_id, $reply->ip_trace_id);
        $this->assertSame($client->id, $request->oauth_client_id);
        $this->assertSame($request->oauth_client_id, $reply->oauth_client_id);
        $this->assertNull($request->user_id);
        $this->assertSame($request->user_id, $reply->user_id);
    }

    public function test_missing_credentials_returns_401_without_audit_records(): void
    {
        $this->getJson('/api/test')->assertUnauthorized();

        $this->assertSame(0, ApiAuditLog::count());
    }

    public function test_expired_token_returns_401_without_audit_records(): void
    {
        $client = Client::factory()->asClientCredentials()->create();
        $expiration = Passport::tokensExpireIn();
        try {
            Passport::tokensExpireIn(now()->subHour());
            $token = $this->issueClientToken($client);
        } finally {
            Passport::tokensExpireIn($expiration);
        }
        $this->assertTrue($client->tokens()->sole()->expires_at->isPast());

        $this->withToken($token)->getJson('/api/test')->assertUnauthorized();

        $this->assertSame(0, ApiAuditLog::count());
    }

    public function test_revoked_token_returns_401_without_audit_records(): void
    {
        $client = Client::factory()->asClientCredentials()->create();
        $token = $this->issueClientToken($client);
        $client->tokens()->sole()->update(['revoked' => true]);

        $this->withToken($token)->getJson('/api/test')->assertUnauthorized();

        $this->assertSame(0, ApiAuditLog::count());
    }

    public function test_invalid_signature_returns_401_even_when_token_id_exists(): void
    {
        $client = Client::factory()->asClientCredentials()->create();
        $token = $this->issueClientToken($client);
        $parts = explode('.', $token);
        // Keep all claims, including the persisted token ID, and corrupt only the signature.
        $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';

        $this->withToken(implode('.', $parts))->getJson('/api/test')->assertUnauthorized();

        $this->assertSame(0, ApiAuditLog::count());
    }

    public function test_malformed_token_returns_401_without_audit_records(): void
    {
        $client = Client::factory()->asClientCredentials()->create();
        $this->issueClientToken($client);

        $this->withToken('not-a-jwt')->getJson('/api/test')->assertUnauthorized();

        $this->assertSame(0, ApiAuditLog::count());
    }

    private function getIntegrationResponse(Client $client): TestResponse
    {
        $token = $this->issueClientToken($client);

        return $this->withToken($token)->getJson('/api/test?marker=audit-test')
            ->assertOk()->assertJsonPath('data.oauth_client_id', $client->id);
    }

    private function issueClientToken(Client $client): string
    {
        // Ephemeral keys and a real token grant exercise the existing route without live credentials.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        config()->set('passport.private_key', $privateKey);
        config()->set('passport.public_key', $details['key']);

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ])->assertOk();
        $token = $tokenResponse->json('access_token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }

    public function test_authenticated_request_redacts_sensitive_audit_data(): void
    {
        $client = Client::factory()->asClientCredentials()->create();
        $token = $this->issueClientToken($client);

        $this->withToken($token)
            ->getJson('/api/test?password=secret123&marker=audit-test')
            ->assertOk();

        $request = ApiAuditLog::where(
            'type',
            ApiAuditLogType::REQUEST->value
        )->sole();

        $this->assertSame(
            '********',
            $request->content->password
        );
        $this->assertSame(
            '********',
            $request->meta->parameters->password
        );
        $this->assertSame(
            '********',
            $request->meta->headers->authorization
        );

        $this->assertStringNotContainsString(
            'secret123',
            $request->resource
        );
    }
}
