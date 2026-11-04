<?php

namespace Tests\Feature\Console;

use App\Enums\DatabaseConnection;
use App\Models\ConsoleCommandLog;
use App\Notifications\SlackNotification;
use App\Services\DatabaseLogServices\ConsoleCommandLogService;
use App\Services\LogSystem;
use Carbon\Carbon;
use Closure;
use Error;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\DeadlockException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Tests\Fixtures\Console\LifecycleCommand;
use Tests\Support\Console\Heartbeat;
use Tests\TestCase;
use Throwable;

require_once __DIR__.'/../../Support/Console/Heartbeat.php';

class CommandTest extends TestCase
{
    private mixed $previousConsoleService;

    private StreamOutput $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConsoleService = (new ReflectionProperty(LogSystem::class, 'console'))->getValue();
        $this->useConsoleService(new ConsoleCommandLogService);
        Heartbeat::reset(active: true);
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00'));
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('c', 32)));
        config()->set('engineering.notification_channel', 'slack');
        config()->set('engineering.log_slack_notifications_enabled', true);
        config()->set('logging.channels.slack_scheduled_command.enabled', true);
        config()->set('logging.channels.slack_scheduled_command.url', 'https://slack.invalid/test-command');
        config()->set('app.console_command_execution_heartbeat_interval_seconds', 30);
        Log::spy();
    }

    protected function tearDown(): void
    {
        try {
            (new ReflectionProperty(LogSystem::class, 'console'))->setValue(null, $this->previousConsoleService);
            Heartbeat::reset();
            $this->travelBack();
        } finally {
            parent::tearDown();
        }
    }

    public function test_success_persists_parameters_result_memory_and_statistics(): void
    {
        $command = $this->command();

        $exitCode = $this->runCommand($command, ['subject' => 'orders', '--mode' => 'batch']);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $command->fulfillCalls);
        $entry = ConsoleCommandLog::query()->sole();
        $this->assertSame('test:base-command-lifecycle', $entry->name);
        $this->assertSame(1, (int) $entry->success);
        $this->assertSame(['processed' => 3, 'details' => ['source' => 'fixture']], json_decode(json_encode($entry->meta), true));
        $parameters = json_decode($entry->parameters, true);
        $this->assertSame('orders', $parameters['arguments']['subject']);
        $this->assertSame('batch', $parameters['options']['mode']);
        $this->assertNotEmpty($entry->executed_on_host);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2} (KB|MB|GB|TB)$/', $entry->memory_usage_start);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2} (KB|MB|GB|TB)$/', $entry->memory_usage_end);
        $this->assertStatisticsDisplayed();
        $this->assertStringContainsString('Command ID: '.$entry->id, $this->outputContent());

        Notification::assertSentOnDemand(SlackNotification::class, function (SlackNotification $notification, array $channels, AnonymousNotifiable $recipient) use ($entry): bool {
            $message = $notification->toSlack($recipient);
            $fields = $message->attachments[0]->fields;

            return $channels === ['slack']
                && $recipient->routes['slack'] === 'https://slack.invalid/test-command'
                && $fields['console_command_log_id'] === $entry->id
                && $fields['processed'] === 3
                && $fields['details'] === ['source' => 'fixture']
                && isset($fields['command_duration'], $fields['memory_usage']);
        });
    }

    public function test_empty_array_is_a_successful_result(): void
    {
        $command = $this->command(fn (): array => []);

        $this->assertSame(0, $this->runCommand($command));

        $entry = ConsoleCommandLog::query()->sole();
        $this->assertSame(1, (int) $entry->success);
        $this->assertSame([], $entry->meta);
    }

    #[DataProvider('executionFailures')]
    public function test_execution_failure_is_persisted_and_the_original_throwable_is_preserved(string $exceptionClass): void
    {
        $failure = new $exceptionClass('Business execution failed', 42);
        $command = $this->command(function () use ($failure): array {
            throw $failure;
        });

        $caught = $this->captureFailure(fn () => $this->runCommand($command));

        $this->assertSame($failure, $caught);
        $this->assertSame(1, $command->fulfillCalls);
        $entry = ConsoleCommandLog::query()->sole();
        $this->assertSame(0, (int) $entry->success);
        $this->assertSame(42, $entry->meta->code);
        $this->assertSame('Business execution failed', $entry->meta->exception);
        $this->assertNotEmpty($entry->memory_usage_end);
        $this->assertStatisticsDisplayed();
        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Business execution failed'))->once();
    }

    public static function executionFailures(): array
    {
        return ['exception' => [RuntimeException::class], 'PHP error' => [Error::class]];
    }

    public function test_start_failure_still_executes_and_displays_statistics_without_a_log(): void
    {
        $service = Mockery::mock(ConsoleCommandLogService::class);
        $service->shouldReceive('start')->once()->andThrow(new RuntimeException('Log database unavailable'));
        $service->shouldNotReceive('end');
        $this->useConsoleService($service);
        $command = $this->command();

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame(1, $command->fulfillCalls);
        $this->assertNull($command->getConsoleLogEntry());
        $this->assertSame(0, ConsoleCommandLog::count());
        $this->assertStatisticsDisplayed();
        $this->assertStringNotContainsString('Command ID:', $this->outputContent());
        Notification::assertSentOnDemand(SlackNotification::class, fn (SlackNotification $notification, array $channels, AnonymousNotifiable $recipient): bool => $notification->toSlack($recipient)->attachments[0]->fields['console_command_log_id'] === null);
    }

    public function test_start_failure_does_not_replace_an_execution_exception_even_if_warning_logging_fails(): void
    {
        $service = Mockery::mock(ConsoleCommandLogService::class);
        $service->shouldReceive('start')->once()->andThrow(new RuntimeException('Log database unavailable'));
        $service->shouldNotReceive('end');
        $this->useConsoleService($service);
        Log::shouldReceive('warning')->andThrow(new RuntimeException('Warning logger unavailable'));
        $failure = new RuntimeException('Business failure');
        $command = $this->command(function () use ($failure): array {
            throw $failure;
        });

        $this->assertSame($failure, $this->captureFailure(fn () => $this->runCommand($command)));

        $this->assertSame(1, $command->fulfillCalls);
        $this->assertStatisticsDisplayed();
    }

    public function test_end_failure_does_not_turn_successful_work_into_a_command_failure(): void
    {
        $this->failFinalization();
        $command = $this->command();

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame(1, $command->fulfillCalls);
        $this->assertNull(ConsoleCommandLog::query()->sole()->memory_usage_end);
        $this->assertStatisticsDisplayed();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Finalization unavailable'))->once();
        Notification::assertSentOnDemandOnce(SlackNotification::class);
    }

    public function test_end_failure_does_not_replace_the_original_execution_exception(): void
    {
        $this->failFinalization();
        $failure = new RuntimeException('Original business exception');
        $command = $this->command(function () use ($failure): array {
            throw $failure;
        });

        $caught = $this->captureFailure(fn () => $this->runCommand($command));

        $this->assertSame(1, $command->fulfillCalls);
        $this->assertNull(ConsoleCommandLog::query()->sole()->memory_usage_end);
        $this->assertSame($failure, $caught);
    }

    public function test_slack_delivery_failure_does_not_interrupt_successful_execution(): void
    {
        $command = $this->command();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()
            ->with(Mockery::on(fn (AnonymousNotifiable $recipient): bool => $recipient->routes['slack'] === 'https://slack.invalid/test-command'), Mockery::type(SlackNotification::class))
            ->andThrow(new RuntimeException('Slack delivery unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame(1, (int) ConsoleCommandLog::query()->sole()->success);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Slack delivery unavailable')->once();
    }

    public function test_notification_setup_failure_does_not_interrupt_successful_execution(): void
    {
        $command = $this->command();
        config()->set('logging.channels.slack_scheduled_command.url', null);

        $caught = $this->captureFailure(fn () => $this->runCommand($command));

        $this->assertSame(1, (int) ConsoleCommandLog::query()->sole()->success);
        $this->assertNull($caught, 'Best-effort notifications must not escape the command: '.($caught?->getMessage() ?? ''));
    }

    public function test_disabled_slack_notifications_are_not_dispatched(): void
    {
        $command = $this->command();
        config()->set('logging.channels.slack_scheduled_command.enabled', false);

        $this->assertSame(0, $this->runCommand($command));

        Notification::assertNothingSent();
        $this->assertSame(1, (int) ConsoleCommandLog::query()->sole()->success);
    }

    public function test_heartbeat_starts_at_the_configured_interval_and_stops_on_success(): void
    {
        $this->requireSignalConstants();
        config()->set('engineering.console_command_execution_heartbeat_interval_seconds', 17);
        $command = $this->command(function (): array {
            $this->assertSame([17], Heartbeat::$alarms);
            $this->assertNotNull(Heartbeat::$callback);

            return [];
        });

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame([17, 0], Heartbeat::$alarms);
        $this->assertSame(SIG_IGN, end(Heartbeat::$handlers));
    }

    public function test_heartbeat_cleanup_occurs_after_an_execution_failure(): void
    {
        $this->requireSignalConstants();
        $failure = new RuntimeException('Business failure');
        $command = $this->command(function () use ($failure): array {
            throw $failure;
        });

        $this->assertSame($failure, $this->captureFailure(fn () => $this->runCommand($command)));

        $this->assertSame([30, 0], Heartbeat::$alarms);
        $this->assertSame(SIG_IGN, end(Heartbeat::$handlers));
    }

    public function test_heartbeat_is_skipped_without_pcntl_support(): void
    {
        Heartbeat::$available = false;
        $command = $this->command();

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame([], Heartbeat::$alarms);
        $this->assertSame([], Heartbeat::$handlers);
        $this->assertSame(1, $command->fulfillCalls);
    }

    public function test_heartbeat_is_not_started_without_an_execution_log(): void
    {
        $service = Mockery::mock(ConsoleCommandLogService::class);
        $service->shouldReceive('start')->once()->andThrow(new RuntimeException('No log entry'));
        $service->shouldNotReceive('end');
        $this->useConsoleService($service);
        $command = $this->command(function (): array {
            $this->assertNull(Heartbeat::$callback);
            $this->assertSame([], Heartbeat::$alarms);

            return [];
        });

        $this->assertSame(0, $this->runCommand($command));

        $this->assertNull(Heartbeat::$callback);
    }

    public function test_heartbeat_refreshes_the_running_log_and_rearms_without_waiting(): void
    {
        $this->requireSignalConstants();
        $command = $this->command(function (LifecycleCommand $command): array {
            $this->travelTo(Carbon::parse('2026-11-03 12:01:00'));
            (Heartbeat::$callback)();

            $entry = $command->getConsoleLogEntry();
            $this->assertSame('2026-11-03 12:01:00', $entry->updated_at->toDateTimeString());
            $this->assertNull($entry->memory_usage_end);
            $this->assertSame([30, 30], Heartbeat::$alarms);

            return [];
        });

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame([30, 30, 0], Heartbeat::$alarms);
    }

    public function test_heartbeat_touch_failure_is_ignored_and_the_alarm_is_rearmed(): void
    {
        $this->requireSignalConstants();
        $model = Mockery::mock(ConsoleCommandLog::class)->makePartial();
        $model->setRawAttributes(['id' => 123]);
        $model->shouldReceive('touch')->once()->andThrow(new RuntimeException('Heartbeat database unavailable'));
        $service = Mockery::mock(ConsoleCommandLogService::class);
        $service->shouldReceive('start')->once()->andReturn($model);
        $service->shouldReceive('end')->once()->with(123, Mockery::type('int'), true, [])->andReturn(true);
        $this->useConsoleService($service);
        $command = $this->command(function (): array {
            (Heartbeat::$callback)();

            return [];
        });

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame(1, $command->fulfillCalls);
        $this->assertSame([30, 30, 0], Heartbeat::$alarms);
    }

    public function test_heartbeat_setup_failure_does_not_prevent_work_from_running(): void
    {
        $this->requireSignalConstants();
        Heartbeat::$startFailure = new RuntimeException('Signal setup unavailable');
        $command = $this->command();

        $caught = $this->captureFailure(fn () => $this->runCommand($command));

        $this->assertSame(1, $command->fulfillCalls, 'Best-effort heartbeat setup must not prevent fulfill().');
        $this->assertNull($caught);
        $this->assertSame(1, (int) ConsoleCommandLog::query()->sole()->success);
    }

    public function test_heartbeat_cleanup_failure_does_not_interrupt_success(): void
    {
        $this->requireSignalConstants();
        Heartbeat::$stopFailure = new RuntimeException('Alarm cleanup unavailable');
        $command = $this->command();

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame(1, (int) ConsoleCommandLog::query()->sole()->success);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Alarm cleanup unavailable'))->once();
    }

    public function test_heartbeat_cleanup_occurs_even_if_log_finalization_fails(): void
    {
        $this->requireSignalConstants();
        $this->failFinalization();
        $command = $this->command();

        $this->assertSame(0, $this->runCommand($command));

        $this->assertSame([30, 0], Heartbeat::$alarms);
        $this->assertSame(SIG_IGN, end(Heartbeat::$handlers));
    }

    public function test_heartbeat_cleanup_failure_does_not_replace_the_original_exception(): void
    {
        $this->requireSignalConstants();
        Heartbeat::$stopFailure = new RuntimeException('Alarm cleanup unavailable');
        Log::shouldReceive('warning')->andThrow(new RuntimeException('Warning logger unavailable'));
        $failure = new RuntimeException('Original business failure');
        $command = $this->command(function () use ($failure): array {
            throw $failure;
        });

        $this->assertSame($failure, $this->captureFailure(fn () => $this->runCommand($command)));

        $this->assertSame([30, 0], Heartbeat::$alarms);
        $this->assertSame(0, (int) ConsoleCommandLog::query()->sole()->success);
    }

    public function test_quiet_background_execution_still_logs_and_notifies(): void
    {
        $command = $this->command();

        $this->assertSame(0, $this->runCommand($command, verbosity: OutputInterface::VERBOSITY_QUIET));

        $this->assertSame('', $this->outputContent());
        $this->assertSame(1, $command->fulfillCalls);
        $this->assertSame(1, (int) ConsoleCommandLog::query()->sole()->success);
        Notification::assertSentOnDemandOnce(SlackNotification::class);
    }

    public function test_statistics_display_elapsed_time_without_waiting(): void
    {
        $command = $this->command(function (): array {
            $this->travelTo(Carbon::parse('2026-11-03 12:01:01'));

            return [];
        });

        $this->assertSame(0, $this->runCommand($command));

        $this->assertStringContainsString('Execution duration: 00:01:01 (H:i:s)', $this->outputContent());
    }

    public function test_log_entry_access_returns_the_fresh_persisted_model(): void
    {
        $command = $this->command();
        $this->runCommand($command);
        $entry = ConsoleCommandLog::query()->sole();
        $entry->update(['executed_on_host' => 'refreshed-host']);

        $retrieved = $command->getConsoleLogEntry();

        $this->assertInstanceOf(ConsoleCommandLog::class, $retrieved);
        $this->assertTrue($retrieved->is($entry));
        $this->assertSame('refreshed-host', $retrieved->executed_on_host);
    }

    public function test_log_entry_access_returns_null_before_execution(): void
    {
        $command = $this->command();

        $this->assertNull($command->getConsoleLogEntry());
    }

    public function test_log_entry_access_returns_null_if_the_record_was_deleted(): void
    {
        $command = $this->command();
        $this->runCommand($command);
        ConsoleCommandLog::query()->sole()->delete();

        $this->assertNull($command->getConsoleLogEntry());
    }

    public function test_log_entry_retrieval_failure_is_safe_even_if_warning_logging_fails(): void
    {
        $model = Mockery::mock(ConsoleCommandLog::class)->makePartial();
        $model->setRawAttributes(['id' => 456]);
        $model->shouldReceive('fresh')->once()->andThrow(new RuntimeException('Retrieval unavailable'));
        $service = Mockery::mock(ConsoleCommandLogService::class);
        $service->shouldReceive('start')->once()->andReturn($model);
        $service->shouldReceive('end')->once()->andReturn(true);
        $this->useConsoleService($service);
        $command = $this->command();
        $this->runCommand($command);
        Log::shouldReceive('warning')->once()->andThrow(new RuntimeException('Warning logger unavailable'));

        $this->assertNull($command->getConsoleLogEntry());
    }

    public function test_deadlock_returns_a_failure_exit_code_and_records_the_failure(): void
    {
        $command = $this->command(function (): array {
            throw new DeadlockException('Synthetic deadlock');
        });

        $this->assertSame(1, $this->runCommand($command));

        $this->assertSame(0, (int) ConsoleCommandLog::query()->sole()->success);
        Notification::assertNothingSent();
    }

    private function command(?Closure $work = null): LifecycleCommand
    {
        Notification::fake();

        $command = new LifecycleCommand($work);
        $command->setLaravel($this->app);

        return $command;
    }

    private function runCommand(LifecycleCommand $command, array $parameters = [], int $verbosity = OutputInterface::VERBOSITY_NORMAL): int
    {
        $input = new ArrayInput($parameters, $command->getDefinition());
        $input->setInteractive(false);
        $this->output = new StreamOutput(fopen('php://memory', 'w+'), $verbosity);

        return $command->run($input, $this->output);
    }

    private function captureFailure(Closure $action): ?Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }

    private function assertStatisticsDisplayed(): void
    {
        $content = $this->outputContent();
        foreach (['Memory Usage Start:', 'Memory Usage End:', 'Total Memory Usage (Start VS Peak):', 'Execution started at:', 'Execution end at:', 'Execution duration:'] as $label) {
            $this->assertStringContainsString($label, $content);
        }
    }

    private function outputContent(): string
    {
        rewind($this->output->getStream());

        return stream_get_contents($this->output->getStream());
    }

    /** LogSystem caches its service outside the container; restore this seam in tearDown. */
    private function useConsoleService(ConsoleCommandLogService $service): void
    {
        (new ReflectionProperty(LogSystem::class, 'console'))->setValue(null, $service);
    }

    private function failFinalization(): void
    {
        $service = Mockery::mock(ConsoleCommandLogService::class, [])->makePartial();
        $service->shouldReceive('end')->once()->andThrow(new RuntimeException('Finalization unavailable'));
        $this->useConsoleService($service);
    }

    private function requireSignalConstants(): void
    {
        if (! defined('SIGALRM') || ! defined('SIG_IGN')) {
            $this->markTestSkipped('Signal constants are unavailable on this platform; no real PCNTL functions are called.');
        }
    }
}
