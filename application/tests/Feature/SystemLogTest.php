<?php

namespace Tests\Feature;

use App\Enums\DatabaseConnection;
use App\Handlers\LogDatabaseMonologHandler;
use App\Logging\SensitiveDataProcessor;
use App\Models\SystemLog;
use App\Services\LogSystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SystemLogTest extends TestCase
{
    private string $logDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = sys_get_temp_dir().'/system-log-tests-'.Str::uuid();
        File::makeDirectory($this->logDirectory, 0700, true);
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('a', 32)));
        config()->set('engineering.system_logs.sanitize_sensitive_data', true);
        config()->set('logging.default', 'stack');
        config()->set('logging.channels.stack.channels', ['single', 'database']);
        config()->set('logging.channels.fallback.channels', ['single']);
        foreach (['single', 'daily', 'monthly', 'emergency'] as $channel) {
            config()->set('logging.channels.'.$channel.'.path', $this->logDirectory.'/'.$channel.'.log');
        }
        foreach (['single', 'daily', 'database'] as $channel) {
            config()->set('logging.channels.'.$channel.'.level', 'debug');
        }
        foreach (array_keys(Log::getChannels()) as $channel) {
            Log::forgetChannel($channel);
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach (Log::getChannels() as $name => $channel) {
                $channel->getLogger()->close();
                Log::forgetChannel($name);
            }
            File::deleteDirectory($this->logDirectory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_database_channel_persists_message_level_channel_and_context(): void
    {
        Log::channel('database')->info('Order created', ['order_id' => 123, 'active' => true]);

        $stored = SystemLog::query()->sole();
        $this->assertModelExists($stored);
        $this->assertSame('Order created', $stored->message);
        $this->assertSame('200', $stored->level);
        $this->assertSame('info', $stored->level_name);
        $this->assertSame('database', $stored->channel);
        $this->assertSame(123, $stored->context->order_id);
        $this->assertTrue($stored->context->active);
    }

    #[DataProvider('loggingLevels')]
    public function test_all_supported_logging_levels_are_persisted(string $name, int $value): void
    {
        Log::channel('database')->log($name, 'Level test');

        $stored = SystemLog::query()->sole();
        $this->assertSame((string) $value, $stored->level);
        $this->assertSame($name, $stored->level_name);
        $this->assertSame('Level test', $stored->message);
    }

    public static function loggingLevels(): array
    {
        return [
            'debug' => ['debug', 100],
            'info' => ['info', 200],
            'notice' => ['notice', 250],
            'warning' => ['warning', 300],
            'error' => ['error', 400],
            'critical' => ['critical', 500],
            'alert' => ['alert', 550],
            'emergency' => ['emergency', 600],
        ];
    }

    public function test_sensitive_fields_are_sanitized_recursively_and_case_insensitively(): void
    {
        Log::channel('database')->info('Sanitization test', [
            'PASSWORD' => 'synthetic-password',
            'username' => 'test-user',
            'nested' => [
                'Access_Token' => 'synthetic-token',
                'items' => [['CLIENT_SECRET' => 'synthetic-secret', 'quantity' => 2]],
                'active' => true,
            ],
        ]);

        $context = SystemLog::query()->sole()->context;
        $this->assertSame('********', $context->PASSWORD);
        $this->assertSame('test-user', $context->username);
        $this->assertSame('********', $context->nested->Access_Token);
        $this->assertSame('********', $context->nested->items[0]->CLIENT_SECRET);
        $this->assertSame(2, $context->nested->items[0]->quantity);
        $this->assertTrue($context->nested->active);
    }

    public function test_disabling_sanitization_preserves_context_and_placeholder_values(): void
    {
        config()->set('engineering.system_logs.sanitize_sensitive_data', false);

        Log::channel('database')->info('Password {password}', ['password' => 'synthetic-only-value']);

        $stored = SystemLog::query()->sole();
        $this->assertSame('Password synthetic-only-value', $stored->message);
        $this->assertSame('synthetic-only-value', $stored->context->password);
    }

    public function test_direct_service_sanitizes_nested_context_case_insensitively_before_encryption(): void
    {
        $stored = LogSystem::system()->add('Direct service {username}', Level::Info, [
            'PASSWORD' => 'synthetic-password',
            'username' => 'test-user',
            'nested' => ['items' => [['Access_Token' => 'synthetic-token', 'quantity' => 2]]],
        ], 'service-test');

        $this->assertInstanceOf(SystemLog::class, $stored);
        $this->assertModelExists($stored);
        $stored = $stored->fresh();
        $this->assertSame('********', $stored->context->PASSWORD);
        $this->assertSame('********', $stored->context->nested->items[0]->Access_Token);
        $this->assertSame(2, $stored->context->nested->items[0]->quantity);
        $this->assertSame('test-user', $stored->context->username);
        $this->assertSame('Direct service {username}', $stored->message);
        $this->assertSame('200', $stored->level);
        $this->assertSame('service-test', $stored->channel);
        $raw = DB::connection(DatabaseConnection::APPLICATION)->table('system_logs')->find($stored->id);
        $this->assertStringNotContainsString('test-user', $raw->context);
        $this->assertStringNotContainsString('synthetic-password', $raw->context);
    }

    public function test_direct_service_respects_custom_sensitive_fields(): void
    {
        config()->set('engineering.sensitive_data.fields', ['PRIVATE_NOTE']);

        $stored = LogSystem::system()->add('Custom service context', Level::Info, [
            'nested' => ['private_note' => 'synthetic-note'],
            'password' => 'synthetic-unlisted-value',
        ], 'service-test');

        $this->assertInstanceOf(SystemLog::class, $stored);
        $context = $stored->fresh()->context;
        $this->assertSame('********', $context->nested->private_note);
        $this->assertSame('synthetic-unlisted-value', $context->password);
    }

    public function test_direct_service_preserves_context_when_sanitization_is_disabled(): void
    {
        config()->set('engineering.system_logs.sanitize_sensitive_data', false);

        $stored = LogSystem::system()->add('Unmodified {password}', Level::Info, [
            'password' => 'synthetic-password',
            'nested' => ['Access_Token' => 'synthetic-token'],
        ], 'service-test');

        $this->assertInstanceOf(SystemLog::class, $stored);
        $stored = $stored->fresh();
        $this->assertSame('synthetic-password', $stored->context->password);
        $this->assertSame('synthetic-token', $stored->context->nested->Access_Token);
        $this->assertSame('Unmodified {password}', $stored->message);
    }

    public function test_repeated_sanitization_preserves_already_sanitized_context(): void
    {
        $context = ['password' => '********', 'nested' => ['token' => '********', 'count' => 2]];
        $context = sanitizeSensitiveData($context, config('engineering.sensitive_data.fields'));

        $stored = LogSystem::system()->add('Already sanitized', Level::Info, $context, 'service-test');

        $this->assertInstanceOf(SystemLog::class, $stored);
        $this->assertSame($context, json_decode(json_encode($stored->fresh()->context), true));
    }

    public function test_database_handler_sanitizes_context_without_monolog_processors(): void
    {
        $handler = (new LogDatabaseMonologHandler)->setFormatter(new LineFormatter('%message%'));

        $handler->handle(new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'handler-test',
            level: Level::Warning,
            message: 'Direct handler',
            context: ['password' => 'synthetic-password', 'username' => 'test-user'],
        ));

        $stored = SystemLog::query()->sole();
        $this->assertSame('********', $stored->context->password);
        $this->assertSame('test-user', $stored->context->username);
        $this->assertSame('Direct handler', $stored->message);
        $this->assertSame('warning', $stored->level_name);
        $this->assertSame('handler-test', $stored->channel);
    }

    public function test_configured_sensitive_field_list_controls_sanitization(): void
    {
        config()->set('engineering.sensitive_data.fields', ['PRIVATE_NOTE']);

        Log::channel('database')->info('Configured fields', [
            'private_note' => 'synthetic-note',
            'password' => 'synthetic-unlisted-value',
        ]);

        $context = SystemLog::query()->sole()->context;
        $this->assertSame('********', $context->private_note);
        $this->assertSame('synthetic-unlisted-value', $context->password);
    }

    public function test_sensitive_placeholder_is_sanitized_before_message_interpolation(): void
    {
        $sentinel = 'synthetic-placeholder-'.Str::uuid();

        Log::channel('database')->info('Login attempt with password {password}', [
            'password' => $sentinel,
            'username' => 'test-user',
        ]);

        $stored = SystemLog::query()->sole();
        $this->assertSame('Login attempt with password ********', $stored->message);
        $this->assertSame('********', $stored->context->password);
        $this->assertSame('test-user', $stored->context->username);
        $raw = DB::connection(DatabaseConnection::APPLICATION)->table('system_logs')->find($stored->id);
        $this->assertStringNotContainsString($sentinel, $raw->message);
        $this->assertStringNotContainsString($sentinel, $raw->context);
        $this->assertStringNotContainsString($sentinel, json_encode($stored->context));
    }

    public function test_context_is_encrypted_at_rest_and_decrypts_to_sanitized_data(): void
    {
        $marker = 'visible-context-marker-'.Str::uuid();

        Log::channel('database')->info('Encryption test', ['reference' => $marker, 'password' => 'synthetic-password']);

        $stored = SystemLog::query()->sole();
        $this->assertSame($marker, $stored->context->reference);
        $this->assertSame('********', $stored->context->password);
        $raw = DB::connection(DatabaseConnection::APPLICATION)->table('system_logs')->value('context');
        $this->assertIsString($raw);
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString($marker, $raw);
        $this->assertStringNotContainsString('reference', $raw);
        $this->assertStringNotContainsString('synthetic-password', $raw);
    }

    public function test_database_channel_resolves_expected_handler_and_processors(): void
    {
        $logger = Log::channel('database')->getLogger();

        $this->assertCount(1, $logger->getHandlers());
        $this->assertInstanceOf(LogDatabaseMonologHandler::class, $logger->getHandlers()[0]);
        $processors = $logger->getProcessors();
        $sanitizers = array_filter($processors, fn ($processor): bool => $processor instanceof SensitiveDataProcessor);
        $interpolators = array_filter($processors, fn ($processor): bool => $processor instanceof PsrLogMessageProcessor);
        $this->assertCount(1, $sanitizers);
        $this->assertCount(1, $interpolators);
        $this->assertLessThan(array_key_first($interpolators), array_key_first($sanitizers));
    }

    #[DataProvider('databaseAndFileStacks')]
    public function test_default_stack_sanitizes_database_and_file_messages_and_context(string $fileChannel, array $channels): void
    {
        config()->set('logging.channels.stack.channels', $channels);

        Log::info('Password {password} for {username}', ['password' => 'synthetic-stack-password', 'username' => 'test-user']);

        $stored = SystemLog::query()->sole();
        $this->assertSame('Password ******** for test-user', $stored->message);
        $this->assertSame('********', $stored->context->password);
        $paths = File::glob($this->logDirectory.'/'.$fileChannel.'*.log');
        $this->assertCount(1, $paths);
        $file = File::get($paths[0]);
        $this->assertStringContainsString('Password ******** for test-user', $file);
        $this->assertStringContainsString('"password":"********"', $file);
        $this->assertStringContainsString('test-user', $file);
        $this->assertStringNotContainsString('synthetic-stack-password', $file);
    }

    public static function databaseAndFileStacks(): array
    {
        return [
            'single before database' => ['single', ['single', 'database']],
            'database before single' => ['single', ['database', 'single']],
            'daily before database' => ['daily', ['daily', 'database']],
            'database before daily' => ['daily', ['database', 'daily']],
        ];
    }

    #[DataProvider('fileChannels')]
    public function test_file_only_stack_sanitizes_messages_and_context(string $channel): void
    {
        config()->set('logging.channels.stack.channels', [$channel]);

        Log::info('Password {password}', ['password' => 'synthetic-file-password', 'username' => 'test-user']);

        $paths = File::glob($this->logDirectory.'/'.$channel.'*.log');
        $this->assertCount(1, $paths);
        $file = File::get($paths[0]);
        $this->assertStringContainsString('Password ********', $file);
        $this->assertStringContainsString('"password":"********"', $file);
        $this->assertStringContainsString('test-user', $file);
        $this->assertStringNotContainsString('synthetic-file-password', $file);
        $this->assertSame(0, SystemLog::count());
    }

    public static function fileChannels(): array
    {
        return ['single' => ['single'], 'daily' => ['daily']];
    }
}
