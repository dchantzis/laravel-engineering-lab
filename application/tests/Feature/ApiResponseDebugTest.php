<?php

namespace Tests\Feature;

use App\Enums\DatabaseConnection;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

class ApiResponseDebugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('engineering.sensitive_data.fields', ['password', 'private_note']);
        config()->set('engineering.log_all_api_responses', false);

        Route::post('/api/debug-response-test/{failure}', function (string $failure) {
            $connection = DB::connection(DatabaseConnection::APPLICATION);
            $connection->flushQueryLog();
            $connection->enableQueryLog();
            $connection->logQuery('select * from users where email = ?', ['query-secret'], 1.5);
            if ($failure === 'literal') {
                $connection->logQuery("select * from users where password = 'literal-secret'", [], 0.1);
            }

            $exception = $failure === 'query'
                ? new QueryException(DatabaseConnection::APPLICATION, 'select * from users where password = ?', ['exception-secret'], new Exception('database error'))
                : null;

            return (new ApiResponse('result', null, exception: $exception))->toResponse();
        });
    }

    #[DataProvider('sanitisationCases')]
    public function test_admin_debug_respects_sanitisation_and_production_override(string $environment, bool $sanitise, bool $masked, bool $queryException): void
    {
        config()->set('app.env', $environment);
        config()->set('app.debug', false);
        config()->set('app.debug_queries', false);
        config()->set('engineering.sanitise_sensitive_data_in_admin_responses', $sanitise);
        $this->actingAs(User::factory()->make(['is_admin' => true]), 'api');

        $body = ['password' => 'body-secret', 'nested' => ['PRIVATE_NOTE' => 'nested-secret', 'name' => 'visible']];
        $response = $this->postJson('/api/debug-response-test/'.($queryException ? 'query' : 'normal'), $body)->assertOk();

        $response->assertJsonPath('debug.request_body', $masked
            ? ['password' => '********', 'nested' => ['PRIVATE_NOTE' => '********', 'name' => 'visible']]
            : $body);
        $response->assertJsonPath('debug.queries.'.DatabaseConnection::APPLICATION.'.0.query', $masked
            ? 'select * from users where email = ?'
            : "select * from users where email = 'query-secret'");
        $response->assertJsonPath('debug.queries.'.DatabaseConnection::APPLICATION.'.0.time', '1.5ms');

        if ($queryException) {
            $response->assertJsonPath('debug.queries.'.DatabaseConnection::APPLICATION.'.1.query', $masked
                ? 'select * from users where password = ?'
                : "select * from users where password = 'exception-secret'");
            $response->assertJsonPath('debug.queries.'.DatabaseConnection::APPLICATION.'.1.time', '');
        }
    }

    public static function sanitisationCases(): array
    {
        $cases = [];
        foreach (['local', 'staging', 'production'] as $environment) {
            foreach ([true, false] as $sanitise) {
                foreach ([false, true] as $queryException) {
                    $cases[$environment.'-'.($sanitise ? 'enabled' : 'disabled').'-'.($queryException ? 'exception' : 'normal')] = [
                        $environment, $sanitise, $environment === 'production' || $sanitise, $queryException,
                    ];
                }
            }
        }

        return $cases;
    }

    public function test_sanitised_production_debug_can_still_expose_exception_messages_and_sql_literals(): void
    {
        config()->set('app.env', 'production');
        config()->set('engineering.sanitise_sensitive_data_in_admin_responses', true);
        $this->actingAs(User::factory()->make(['is_admin' => true]), 'api');

        $exceptionResponse = $this->postJson('/api/debug-response-test/query')->assertOk();
        $this->assertStringContainsString('exception-secret', $exceptionResponse->json('debug.message'));

        $literalResponse = $this->postJson('/api/debug-response-test/literal')->assertOk();
        $literalResponse->assertJsonPath('debug.queries.'.DatabaseConnection::APPLICATION.'.1.query', "select * from users where password = 'literal-secret'");
    }

    #[DataProvider('nonAdminCases')]
    public function test_non_admin_debug_follows_environment_and_debug_settings(string $environment, bool $debug, bool $debugQueries, bool $hasDebug, bool $hasQueries): void
    {
        config()->set('app.env', $environment);
        config()->set('app.debug', $debug);
        config()->set('app.debug_queries', $debugQueries);
        config()->set('engineering.sanitise_sensitive_data_in_admin_responses', true);
        $this->actingAs(User::factory()->make(['is_admin' => false]), 'api');

        $response = $this->postJson('/api/debug-response-test/normal', ['password' => 'body-secret'])->assertOk();
        if (! $hasDebug) {
            $response->assertJsonMissingPath('debug');

            return;
        }

        $response->assertJsonPath('debug.request_body.password', '********');
        if ($hasQueries) {
            $response->assertJsonPath('debug.queries.'.DatabaseConnection::APPLICATION.'.0.query', 'select * from users where email = ?');
        } else {
            $response->assertJsonMissingPath('debug.queries');
        }
    }

    public static function nonAdminCases(): array
    {
        return [
            'production debug off' => ['production', false, false, false, false],
            'production debug on' => ['production', true, true, false, false],
            'staging debug off' => ['staging', false, true, false, false],
            'local debug without queries' => ['local', true, false, true, false],
            'staging debug with queries' => ['staging', true, true, true, true],
        ];
    }
}
