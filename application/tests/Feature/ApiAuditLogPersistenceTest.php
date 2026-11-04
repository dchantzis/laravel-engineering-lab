<?php

namespace Tests\Feature;

use App\Enums\ApiAuditLogType;
use App\Enums\DatabaseConnection;
use App\Models\ApiAuditLog;
use App\Models\User;
use App\Services\LogSystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

class ApiAuditLogPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('engineering.api_audit_logs_enabled', true);
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('a', 32)));
    }

    public function test_it_persists_api_audit_log_with_encrypted_content_and_metadata(): void
    {
        config()->set('engineering.api_audit_logs_enabled', true);

        $requestUuid = (string) Str::uuid();

        $auditLog = LogSystem::api()->add(
            content: [
                'order_id' => 123,
                'status' => 'created',
            ],
            requestUuid: $requestUuid,
            type: ApiAuditLogType::REQUEST,
            meta: [
                'source' => 'portfolio',
                'version' => 1,
            ],
            httpStatusCode: 200,
            resource: 'api/test',
        );

        $this->assertInstanceOf(ApiAuditLog::class, $auditLog);
        $this->assertNotNull($auditLog->id);
        $this->assertModelExists($auditLog);
        $this->assertDatabaseHas('api_audit_logs', [
            'id' => $auditLog->id,
            'request_uuid' => $requestUuid,
            'type' => ApiAuditLogType::REQUEST->value,
            'resource' => 'api/test',
            'http_status_code' => 200,
            'ip_trace_id' => null,
            'oauth_client_id' => null,
            'user_id' => null,
        ], DatabaseConnection::APPLICATION);

        $stored = ApiAuditLog::findOrFail($auditLog->id);

        $this->assertSame($requestUuid, $stored->request_uuid);
        $this->assertSame(200, $stored->http_status_code);

        $this->assertSame(123, $stored->content->order_id);
        $this->assertSame('created', $stored->content->status);

        $this->assertSame('portfolio', $stored->meta->source);
        $this->assertSame(1, $stored->meta->version);
    }

    public function test_it_does_not_persist_when_api_auditing_is_disabled(): void
    {
        config()->set('engineering.api_audit_logs_enabled', false);

        $result = LogSystem::api()->add(
            content: ['test' => true],
            requestUuid: (string) Str::uuid(),
            type: ApiAuditLogType::REQUEST,
        );

        $this->assertNull($result);
        $this->assertSame(0, ApiAuditLog::count());
    }

    public function test_async_response_preserves_request_correlation_and_payload(): void
    {
        $client = Client::factory()->asClientCredentials()->create();
        $user = User::factory()->connection(DatabaseConnection::APPLICATION)->create();
        $trace = LogSystem::ipTrace()->add('127.0.0.1', 'GET', '/api/test', [], [], [], 'localhost');
        $requestUuid = Str::uuid();
        $service = LogSystem::api();
        $request = $service->add(
            content: ['job' => 'queued'],
            requestUuid: (string) $requestUuid,
            type: ApiAuditLogType::REQUEST,
            ipTraceId: $trace->id,
            oauthClientId: $client->id,
            userId: $user->id,
            resource: 'api/test',
        );
        $this->assertInstanceOf(ApiAuditLog::class, $request);
        $this->assertFalse($service->checkRequestIsProcessed($requestUuid, $client->id));
        $this->assertNull($service->getProcessedRequest($requestUuid, $client->id));

        $result = $service->addForAsyncResponse(['job' => 'finished'], $requestUuid, $request);

        $this->assertInstanceOf(ApiAuditLog::class, $result);
        $this->assertModelExists($result);
        $stored = $result->fresh();
        $this->assertSame(ApiAuditLogType::ASYNC_RESPONSE->value, $stored->type);
        $this->assertSame((string) $requestUuid, $stored->request_uuid);
        $this->assertSame($trace->id, $stored->ip_trace_id);
        $this->assertSame($client->id, $stored->oauth_client_id);
        $this->assertSame($user->id, $stored->user_id);
        $this->assertSame('api/test', $stored->resource);
        $this->assertSame('finished', $stored->content->job);
        $this->assertTrue($service->checkRequestIsProcessed($requestUuid, $client->id));
        $this->assertTrue($stored->is($service->getProcessedRequest($requestUuid, $client->id)));
        $this->assertFalse($service->checkRequestIsProcessed(Str::uuid(), $client->id));
        $this->assertNull($service->getProcessedRequest($requestUuid, (string) Str::uuid()));
    }

    public function test_content_and_metadata_are_encrypted_in_raw_database_columns(): void
    {
        $sentinel = 'test-secret-marker-'.Str::uuid();

        $result = LogSystem::api()->add(
            content: ['secret' => $sentinel],
            requestUuid: (string) Str::uuid(),
            type: ApiAuditLogType::REQUEST,
            meta: ['secret' => $sentinel],
        );

        $this->assertInstanceOf(ApiAuditLog::class, $result);
        $stored = $result->fresh();
        $this->assertSame($sentinel, $stored->content->secret);
        $this->assertSame($sentinel, $stored->meta->secret);
        $raw = DB::connection(DatabaseConnection::APPLICATION)->table('api_audit_logs')->find($result->id);
        $this->assertNotNull($raw);
        foreach (['content', 'meta'] as $column) {
            $this->assertIsString($raw->{$column});
            $this->assertNotSame('', $raw->{$column});
            $this->assertStringNotContainsString($sentinel, $raw->{$column});
            $this->assertStringNotContainsString('secret', $raw->{$column});
        }
    }
}
