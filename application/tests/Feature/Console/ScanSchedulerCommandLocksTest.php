<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ScanSchedulerCommandLocks;
use App\Enums\ScheduledCommandLockStatus as Status;
use App\Models\ConsoleCommandLog;
use App\Services\Admin\SchedulerLockService;
use App\Services\DatabaseLogServices\ConsoleCommandLogService;
use App\Services\LogSystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\Console\Heartbeat;
use Tests\Support\Scheduler\WatchdogTestCase;
use Throwable;

require_once __DIR__.'/../../Support/Console/Heartbeat.php';

class ScanSchedulerCommandLocksTest extends WatchdogTestCase
{
    private mixed $previousConsoleService;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = new ReflectionProperty(LogSystem::class, 'console');
        $this->previousConsoleService = $cache->getValue();
        $cache->setValue(null, new ConsoleCommandLogService);
        Heartbeat::reset(active: true);
        Heartbeat::$available = false;
        config()->set('logging.channels.slack_scheduled_command.enabled', false);
        config()->set('logging.channels.teams_scheduled_command.enabled', false);
        Log::spy();
    }

    protected function tearDown(): void
    {
        try {
            (new ReflectionProperty(LogSystem::class, 'console'))->setValue(null, $this->previousConsoleService);
            Heartbeat::reset();
        } finally {
            parent::tearDown();
        }
    }

    public function test_empty_schedule_reports_no_locks_and_logs_success(): void
    {
        $this->scan()
            ->expectsOutputToContain('Orphan threshold: 5 minute(s)')
            ->expectsOutputToContain('No active mutex locks found on scheduled commands.')
            ->assertSuccessful();

        $entry = ConsoleCommandLog::whereName('app:scan-scheduler-command-locks')->sole();
        $this->assertSame(1, (int) $entry->success);
        $this->assertSame(0, $entry->meta->total);
        $this->assertSame([], $entry->meta->rows);
        $this->assertNotEmpty($entry->memory_usage_end);
        Notification::assertNothingSent();
    }

    #[DataProvider('optionCombinations')]
    public function test_options_are_forwarded_to_the_service(array $options, bool $release, bool $showAll, int $threshold): void
    {
        $service = Mockery::mock(SchedulerLockService::class);
        $service->shouldReceive('scanSchedulerLocks')->once()->with($release, $showAll, $threshold)
            ->andReturn(['total' => 0, 'orphaned' => 0, 'released' => 0, 'rows' => []]);
        $this->app->instance(SchedulerLockService::class, $service);

        $this->scan($options)->expectsOutputToContain('Orphan threshold: '.$threshold.' minute(s)')->assertSuccessful();
    }

    public static function optionCombinations(): array
    {
        return [
            'defaults' => [[], false, false, 5],
            'release only' => [['--release' => true], true, false, 5],
            'all only' => [['--all' => true], false, true, 5],
            'threshold only' => [['--threshold' => '12'], false, false, 12],
            'all flags' => [['--release' => true, '--all' => true, '--threshold' => '8'], true, true, 8],
        ];
    }

    public function test_command_default_uses_the_engineering_threshold(): void
    {
        config()->set('engineering.scheduler_commands_scan_orphan_threshold_minutes', 12);
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();

        $this->scan()->expectsOutputToContain('Orphan threshold: 12 minute(s)')->expectsOutputToContain('LOCKED (active)')->assertSuccessful();

        $this->assertNotNull($this->storedLock($event));
    }

    public function test_default_scan_displays_orphans_without_releasing_them(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();

        $this->scan()->expectsOutputToContain('ORPHANED')->expectsOutputToContain('Total: 1 | Orphaned: 1 | Released: 0')->assertSuccessful();

        $this->assertNotNull($this->storedLock($event));
    }

    public function test_release_option_removes_the_orphan_and_persists_the_report(): void
    {
        $event = $this->scheduled();

        // Simulate a command that started 10 minutes ago.
        $this->travelTo(now()->subMinutes(10));

        $this->acquire($event);
        $this->log(
            ageSeconds: 0,
            createdAt: now()->toDateTimeString()
        );

        // Advance time so the execution becomes stale.
        $this->travelTo(now()->addMinutes(10));

        $this->scan(['--release' => true])
            ->expectsOutputToContain('RELEASED')
            ->expectsOutputToContain('Total: 1 | Orphaned: 1 | Released: 1')
            ->assertSuccessful();

        $this->assertNull($this->storedLock($event));

        $entry = ConsoleCommandLog::whereName('app:scan-scheduler-command-locks')->sole();

        $this->assertSame(1, $entry->meta->released);
        $this->assertSame(Status::RELEASED->value, $entry->meta->rows[0]->status);
    }

    public function test_all_option_shows_unlocked_events_and_their_status(): void
    {
        $this->scheduled();

        $this->scan(['--all' => true])
            ->expectsOutputToContain('watchdog:work')
            ->expectsOutputToContain('Total: 1 | Orphaned: 0 | Released: 0')
            ->assertSuccessful();

        $this->assertSame(Status::OK->value, ConsoleCommandLog::whereName('app:scan-scheduler-command-locks')->sole()->meta->rows[0]->status);
    }

    public function test_custom_threshold_prevents_premature_release(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();

        $this->scan(['--release' => true, '--threshold' => '11'])
            ->expectsOutputToContain('LOCKED (active)')
            ->expectsOutputToContain('Total: 1 | Orphaned: 0 | Released: 0')
            ->assertSuccessful();

        $this->assertNotNull($this->storedLock($event));
    }

    #[DataProvider('invalidThresholds')]
    public function test_invalid_thresholds_are_rejected_before_scanning(string $threshold): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->scan(['--threshold' => $threshold])->run();
    }

    public static function invalidThresholds(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'non-numeric' => ['invalid'],
            'fractional' => ['1.5'],
        ];
    }

    public function test_invalid_threshold_cannot_release_a_healthy_execution(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log(ageSeconds: 0);

        try {
            $this->scan(['--release' => true, '--threshold' => '-1'])->run();
        } catch (\InvalidArgumentException) {
            // Expected: invalid thresholds must abort execution.
        }

        $this->assertNotNull($this->storedLock($event), 'Invalid input must not authorize releasing healthy locks.');
    }

    public function test_service_exception_is_preserved_and_execution_is_logged_as_failed(): void
    {
        $failure = new RuntimeException('Lock storage unavailable');
        $service = Mockery::mock(SchedulerLockService::class);
        $service->shouldReceive('scanSchedulerLocks')->once()->andThrow($failure);
        $this->app->instance(SchedulerLockService::class, $service);
        $caught = null;

        try {
            $this->scan()->run();
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        $this->assertSame($failure, $caught);
        $entry = ConsoleCommandLog::whereName('app:scan-scheduler-command-locks')->sole();
        $this->assertSame(0, (int) $entry->success);
        $this->assertSame('Lock storage unavailable', $entry->meta->exception);
        $this->assertNotEmpty($entry->memory_usage_end);
        Notification::assertNothingSent();
    }

    public function test_partial_release_errors_are_displayed_in_the_report(): void
    {
        $service = Mockery::mock(SchedulerLockService::class);
        $service->shouldReceive('scanSchedulerLocks')->once()->with(true, false, 5)->andReturn([
            'total' => 1,
            'orphaned' => 1,
            'released' => 0,
            'rows' => [[
                'command' => 'php artisan watchdog:work',
                'expression' => '* * * * *',
                'status' => Status::RELEASE_FAILED->value,
                'mutex_lock_key' => 'watchdog-tests:failed',
                'lock_expires' => '2026-11-03 13:00:00',
                'last_heartbeat' => '2026-11-03 11:00:00',
                'stale_minutes' => 60,
                'host' => 'worker-a',
                'log_id' => 123,
            ]],
        ]);
        $this->app->instance(SchedulerLockService::class, $service);

        $this->scan(['--release' => true])
            ->expectsOutputToContain('RELEASE FAILED')
            ->expectsOutputToContain('Total: 1 | Orphaned: 1 | Released: 0')
            ->assertSuccessful();

        $entry = ConsoleCommandLog::whereName('app:scan-scheduler-command-locks')->sole();
        $this->assertSame(Status::RELEASE_FAILED->value, $entry->meta->rows[0]->status);
    }

    private function scan(array $options = []): PendingCommand
    {
        Notification::fake();
        Artisan::registerCommand($this->app->make(ScanSchedulerCommandLocks::class));

        return $this->artisan('app:scan-scheduler-command-locks', $options + ['--no-interaction' => true]);
    }
}
