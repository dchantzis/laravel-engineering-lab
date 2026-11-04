<?php

namespace Tests\Support\Scheduler;

use App\Enums\DatabaseConnection;
use App\Models\ConsoleCommandLog;
use App\Services\Admin\SchedulerLockService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Console\Commands\WatchdogWorkCommand;
use Illuminate\Support\Facades\Artisan;

abstract class WatchdogTestCase extends TestCase
{
    protected Schedule $schedule;

    protected SchedulerLockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'watchdog:work',
            'watchdog:active',
            'watchdog:orphaned',
            'watchdog:unknown',
            'watchdog:unlocked',
            'watchdog:failed',
            'watchdog:released',
         ] as $name) {
            $command = new WatchdogWorkCommand();
            $command->setName($name);

            Artisan::registerCommand($command);
        }

        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));
        config()->set('cache.default', 'database');
        config()->set('cache.prefix', 'watchdog-tests:');
        config()->set('cache.stores.database.connection', DatabaseConnection::APPLICATION);
        config()->set('cache.stores.database.lock_connection', DatabaseConnection::APPLICATION);
        config()->set('cache.stores.database.table', 'cache');
        config()->set('cache.stores.database.lock_table', 'cache_locks');
        config()->set('cache.stores.database.lock_lottery', [0, 100]);
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('w', 32)));
        Cache::forgetDriver('database');

        $this->schedule = new Schedule('UTC');
        $this->app->instance(Schedule::class, $this->schedule);
        $this->service = new SchedulerLockService;
    }

    protected function tearDown(): void
    {
        try {
            Cache::forgetDriver('database');
            $this->travelBack();
        } finally {
            parent::tearDown();
        }
    }

    protected function scheduled(string $command = 'watchdog:work'): Event
    {
        return $this->schedule->command($command)->everyMinute()->withoutOverlapping(60);
    }

    protected function acquire(Event $event): void
    {
        $this->assertTrue($event->mutex->create($event));
    }

    protected function log(
        string $name = 'watchdog:work',
        int $ageSeconds = 600,
        array $arguments = [],
        array $options = [],
        bool $completed = false,
        ?string $createdAt = null,
        string $host = 'worker-a'
    ): ConsoleCommandLog {
        $entry = new ConsoleCommandLog;
        $entry->forceFill([
            'name' => $name,
            'parameters' => json_encode(['arguments' => ['command' => $name] + $arguments, 'options' => $options], JSON_THROW_ON_ERROR),
            'memory_usage_start' => '4.00 MB',
            'memory_usage_end' => $completed ? '8.00 MB' : null,
            'success' => $completed,
            'executed_on_host' => $host,
            'created_at' => $createdAt ?? now()->subHour(),
            'updated_at' => now()->subSeconds($ageSeconds),
        ])->save();

        return $entry;
    }

    protected function lockKey(Event $event): string
    {
        return config('cache.prefix').$event->mutexName();
    }

    protected function storedLock(Event $event): ?object
    {
        return DB::connection(DatabaseConnection::APPLICATION)->table('cache_locks')->where('key', $this->lockKey($event))->first();
    }
}
