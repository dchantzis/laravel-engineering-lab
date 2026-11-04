<?php

namespace Tests\Feature\Admin;

use App\Enums\DatabaseConnection;
use App\Enums\ScheduledCommandLockStatus as Status;
use App\Repositories\ConsoleCommandLogRepository;
use App\Services\Admin\SchedulerLockService;
use Carbon\Carbon;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Scheduling\CacheSchedulingMutex;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\Scheduler\InterleavingEventMutex;
use Tests\Support\Scheduler\WatchdogTestCase;

class SchedulerLockServiceTest extends WatchdogTestCase
{
    public function test_empty_schedule_returns_an_empty_report_without_reading_the_lock_table(): void
    {
        Schema::connection(DatabaseConnection::APPLICATION)->drop('cache_locks');

        $this->assertSame(['total' => 0, 'orphaned' => 0, 'released' => 0, 'rows' => []], $this->service->scanSchedulerLocks());
    }

    public function test_database_overlap_mutex_uses_the_prefixed_lock_table_and_expiry_in_seconds(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $before = $this->storedLock($event);

        $result = $this->service->scanSchedulerLocks();

        $this->assertInstanceOf(DatabaseStore::class, Cache::store()->getStore());
        $this->assertSame(0, DB::connection(DatabaseConnection::APPLICATION)->table('cache')->count());
        $this->assertSame('watchdog-tests:'.$event->mutexName(), $before->key);
        $this->assertSame(now()->timestamp + 3600, (int) $before->expiration);
        $this->assertSame((array) $before, (array) $this->storedLock($event), 'Read-only scanning must not acquire, renew, or change the owner.');
        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
        $this->assertSame($before->key, $result['rows'][0]['mutex_lock_key']);
        $this->assertSame(now()->timestamp + 3600, Carbon::parse($result['rows'][0]['lock_expires'])->timestamp);
    }

    public function test_missing_mutexes_are_hidden_by_default_and_included_with_all(): void
    {
        $this->scheduled();

        $this->assertSame([], $this->service->scanSchedulerLocks()['rows']);
        $result = $this->service->scanSchedulerLocks(showAll: true);

        $this->assertSame(1, $result['total']);
        $row = $result['rows'][0];
        $this->assertSame(Status::OK->value, $row['status']);
        foreach (['mutex_lock_key', 'lock_expires', 'last_heartbeat', 'stale_minutes', 'host', 'log_id'] as $field) {
            $this->assertSame('-', $row[$field]);
        }
        $this->assertArrayNotHasKey('_has_mutex', $row);
    }

    #[DataProvider('heartbeatAges')]
    public function test_heartbeat_threshold_is_inclusive_and_uses_whole_elapsed_minutes(int $ageSeconds, string $status, int $minutes): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $entry = $this->log(ageSeconds: $ageSeconds);

        $result = $this->service->scanSchedulerLocks(thresholdMinutes: 5);

        $this->assertSame($status, $result['rows'][0]['status']);
        $this->assertSame($minutes, $result['rows'][0]['stale_minutes']);
        $this->assertSame($entry->id, $result['rows'][0]['log_id']);
        $this->assertSame('worker-a', $result['rows'][0]['host']);
        $this->assertSame($entry->updated_at->format('Y-m-d H:i:s'), $result['rows'][0]['last_heartbeat']);
        $this->assertSame($status === Status::ORPHANED->value ? 1 : 0, $result['orphaned']);
        $this->assertNotNull($this->storedLock($event));
    }

    public static function heartbeatAges(): array
    {
        return [
            'fresh' => [0, Status::LOCKED_ACTIVE->value, 0],
            'just below threshold' => [299, Status::LOCKED_ACTIVE->value, 4],
            'exact threshold' => [300, Status::ORPHANED->value, 5],
            'fractional minute above threshold' => [359, Status::ORPHANED->value, 5],
            'future timestamp' => [-60, Status::LOCKED_ACTIVE->value, -1],
        ];
    }

    public function test_explicit_threshold_overrides_the_default(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log(ageSeconds: 600);

        $this->assertSame(Status::LOCKED_ACTIVE->value, $this->service->scanSchedulerLocks(thresholdMinutes: 11)['rows'][0]['status']);
        $this->assertSame(Status::ORPHANED->value, $this->service->scanSchedulerLocks(thresholdMinutes: 10)['rows'][0]['status']);
    }

    public function test_service_default_uses_the_configured_engineering_threshold(): void
    {
        config()->set('engineering.scheduler_commands_scan_orphan_threshold_minutes', 20);
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log(ageSeconds: 600);

        $this->assertSame(Status::LOCKED_ACTIVE->value, $this->service->scanSchedulerLocks()['rows'][0]['status']);
    }

    public function test_orphaned_locks_are_not_released_during_a_read_only_scan(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();

        $result = $this->service->scanSchedulerLocks();

        $this->assertSame(1, $result['orphaned']);
        $this->assertSame(0, $result['released']);
        $this->assertSame(Status::ORPHANED->value, $result['rows'][0]['status']);
        $this->assertNotNull($this->storedLock($event));
    }

    public function test_release_removes_only_the_orphaned_overlap_lock_and_keeps_the_execution_log(): void
    {
        $event = $this->scheduled();

        // The execution starts 10 minutes before the watchdog scan.
        $this->travelTo(now()->subMinutes(10));
        $this->acquire($event);

        // The execution log is created immediately after acquiring the mutex.
        $entry = $this->log(
            ageSeconds: 0,
            createdAt: now()->toDateTimeString()
        );

        // The execution stops sending heartbeats.
        $this->travelTo(now()->addMinutes(10));

        $unrelated = Cache::lock('unrelated-job', 3600);
        $this->assertTrue($unrelated->get());

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame(1, $result['orphaned']);
        $this->assertSame(1, $result['released']);
        $this->assertSame(Status::RELEASED->value, $result['rows'][0]['status']);
        $this->assertNull($this->storedLock($event));
        $this->assertTrue($unrelated->isLocked());
        $this->assertModelExists($entry);
        $this->assertNull($entry->fresh()->memory_usage_end);
        $this->assertSame([], $this->service->scanSchedulerLocks()['rows']);
    }

    public function test_release_preserves_active_locks_and_locks_without_execution_evidence(): void
    {
        $active = $this->scheduled('watchdog:active');
        $unknown = $this->scheduled('watchdog:unknown');
        $this->acquire($active);
        $this->acquire($unknown);
        $this->log(name: 'watchdog:active', ageSeconds: 20);

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame([Status::LOCKED_ACTIVE->value, Status::LOCKED->value], array_column($result['rows'], 'status'));
        $this->assertSame(0, $result['orphaned']);
        $this->assertSame(0, $result['released']);
        $this->assertNotNull($this->storedLock($active));
        $this->assertNotNull($this->storedLock($unknown));
    }

    public function test_completed_and_differently_named_logs_are_not_used_as_orphan_evidence(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log(completed: true);
        $this->log(name: 'watchdog:another');

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
        $this->assertSame('-', $result['rows'][0]['log_id']);
        $this->assertNotNull($this->storedLock($event));
    }

    public function test_mixed_reports_count_displayed_events_and_include_every_status_without_internal_fields(): void
    {
        foreach (['active', 'orphaned', 'unknown'] as $name) {
            $this->acquire($this->scheduled('watchdog:'.$name));
        }
        $this->scheduled('watchdog:unlocked');
        $this->log(name: 'watchdog:active', ageSeconds: 1);
        $this->log(name: 'watchdog:orphaned');

        $locked = $this->service->scanSchedulerLocks();
        $all = $this->service->scanSchedulerLocks(showAll: true);

        $this->assertSame(3, $locked['total']);
        $this->assertSame(4, $all['total']);
        $this->assertSame(1, $all['orphaned']);
        $this->assertSame(0, $all['released']);
        $this->assertSame([Status::LOCKED_ACTIVE->value, Status::ORPHANED->value, Status::LOCKED->value, Status::OK->value], array_column($all['rows'], 'status'));
        foreach ($all['rows'] as $row) {
            $this->assertSame(['command', 'expression', 'status', 'mutex_lock_key', 'lock_expires', 'last_heartbeat', 'stale_minutes', 'host', 'log_id'], array_keys($row));
        }
    }

    public function test_expired_database_rows_are_not_treated_as_held_mutexes(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();
        DB::connection(DatabaseConnection::APPLICATION)->table('cache_locks')->where('key', $this->lockKey($event))->update(['expiration' => now()->timestamp]);

        $result = $this->service->scanSchedulerLocks(release: true, showAll: true);

        $this->assertSame(Status::OK->value, $result['rows'][0]['status']);
        $this->assertSame(0, $result['orphaned']);
        $this->assertSame(0, $result['released']);
    }

    public function test_newest_running_execution_wins_over_an_older_stale_execution(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log(createdAt: '2026-11-03 10:00:00');
        $fresh = $this->log(ageSeconds: 10, createdAt: '2026-11-03 11:59:00', host: 'worker-b');
        $this->log(completed: true, createdAt: '2026-11-03 12:00:00');

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame(Status::LOCKED_ACTIVE->value, $result['rows'][0]['status']);
        $this->assertSame($fresh->id, $result['rows'][0]['log_id']);
        $this->assertSame('worker-b', $result['rows'][0]['host']);
        $this->assertNotNull($this->storedLock($event));
    }

    public function test_repository_excludes_completed_rows_and_applies_host_and_execution_filters(): void
    {
        $included = $this->log(name: 'watchdog:first', host: 'worker-a');
        $excluded = $this->log(name: 'watchdog:excluded', host: 'worker-a');
        $this->log(name: 'watchdog:first', host: 'worker-b');
        $this->log(name: 'watchdog:first', host: 'worker-a', completed: true);

        $rows = ConsoleCommandLogRepository::getCurrentlyRunning(hostname: 'worker-a', excludeId: $excluded->id);

        $this->assertSame([$included->id], $rows->modelKeys());
        $this->assertSame([$included->id], ConsoleCommandLogRepository::getCurrentlyRunning(hostname: 'worker-a', excludeCommandName: 'watchdog:excluded')->modelKeys());
    }

    public function test_positional_and_quoted_arguments_match_the_correct_execution(): void
    {
        $event = $this->scheduled('watchdog:work "north region" --quiet');
        $this->acquire($event);
        $matching = $this->log(
            arguments: ['source' => 'north region'],
            options: ['quiet' => true],
            createdAt: '2026-11-03 10:00:00'
        );
        $this->log(arguments: ['source' => 'south region'], ageSeconds: 1, createdAt: '2026-11-03 11:59:00');

        $result = $this->service->scanSchedulerLocks();

        $this->assertSame($matching->id, $result['rows'][0]['log_id']);
        $this->assertSame(Status::ORPHANED->value, $result['rows'][0]['status']);
    }

    public function test_unmatched_positional_arguments_do_not_authorize_release(): void
    {
        $event = $this->scheduled('watchdog:work alpha');
        $this->acquire($event);
        $this->log(arguments: ['source' => 'beta']);

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
        $this->assertNotNull($this->storedLock($event));
    }

    public function test_malformed_log_parameters_do_not_match_required_positional_arguments(): void
    {
        $event = $this->scheduled('watchdog:work alpha');
        $this->acquire($event);
        $this->log()->update(['parameters' => '{invalid-json']);

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame('-', $result['rows'][0]['log_id']);
        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
        $this->assertNotNull($this->storedLock($event));
    }

    public function test_reordered_positional_arguments_do_not_authorize_release(): void
    {
        $event = $this->scheduled('watchdog:work source destination');
        $this->acquire($event);
        $this->log(arguments: [
            'source' => 'destination',
            'destination' => 'source',
        ]);

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertNotNull($this->storedLock($event), 'Arguments with different positions identify different executions.');
        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
    }

    public function test_zero_argument_is_not_discarded_during_execution_matching(): void
    {
        $event = $this->scheduled('watchdog:work 0');
        $this->acquire($event);
        $this->log(arguments: ['source' => '1']);

        $this->service->scanSchedulerLocks(release: true);

        $this->assertNotNull($this->storedLock($event), 'The argument "0" must not match a different format.');
    }

    public function test_different_option_values_cannot_authorize_release_of_an_active_execution(): void
    {
        $event = $this->scheduled('watchdog:work --format=alpha');
        $this->acquire($event);
        $this->log(ageSeconds: 1, options: ['format' => 'alpha'], createdAt: '2026-11-03 10:00:00');
        $this->log(options: ['format' => 'beta'], createdAt: '2026-11-03 11:00:00');

        $this->service->scanSchedulerLocks(release: true);

        $this->assertNotNull($this->storedLock($event), 'An unrelated stale option variant must not authorize release.');
    }

    public function test_separate_option_values_are_not_mistaken_for_positional_arguments(): void
    {
        $event = $this->scheduled('watchdog:work --format alpha');
        $this->acquire($event);
        $entry = $this->log(options: ['format' => 'alpha']);

        $result = $this->service->scanSchedulerLocks();

        $this->assertSame($entry->id, $result['rows'][0]['log_id']);
    }

    public function test_same_second_execution_ties_select_the_newest_log_id(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();
        $fresh = $this->log(ageSeconds: 1);

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame($fresh->id, $result['rows'][0]['log_id']);
        $this->assertNotNull($this->storedLock($event));
    }

    public function test_a_heartbeat_refresh_during_the_scan_prevents_release(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $entry = $this->log();
        $event->mutex = new InterleavingEventMutex($event->mutex, fn () => $entry->touch());

        $this->service->scanSchedulerLocks(release: true);

        $this->assertSame(now()->toDateTimeString(), $entry->fresh()->updated_at->toDateTimeString());
        $this->assertNotNull($this->storedLock($event), 'Recheck the heartbeat before destructive release.');
    }

    public function test_a_new_lock_owner_during_the_scan_is_not_force_released(): void
    {
        $event = $this->scheduled();
        $this->acquire($event);
        $this->log();
        $originalMutex = $event->mutex;
        $replacement = Cache::lock($event->mutexName(), 3600, 'replacement-owner');
        $event->mutex = new InterleavingEventMutex($originalMutex, function () use ($originalMutex, $event, $replacement): void {
            $originalMutex->forget($event);
            $this->assertTrue($replacement->get());
        });

        $this->service->scanSchedulerLocks(release: true);

        $this->assertSame('replacement-owner', $this->storedLock($event)?->owner, 'A stale snapshot must not authorize deleting a newly acquired lock.');
    }

    public function test_release_errors_are_reported_and_do_not_stop_other_orphan_releases(): void
    {
        Log::spy();

        $failed = $this->scheduled('watchdog:failed');
        $released = $this->scheduled('watchdog:released');

        // Both commands acquire their mutexes 10 minutes before the scan.
        $this->travelTo(now()->subMinutes(10));

        $this->acquire($failed);
        $this->acquire($released);

        // Both execution logs are created after their mutexes are acquired.
        $this->log(
            name: 'watchdog:failed',
            ageSeconds: 0,
            createdAt: now()->toDateTimeString()
        );

        $this->log(
            name: 'watchdog:released',
            ageSeconds: 0,
            createdAt: now()->toDateTimeString()
        );

        // Simulate 10 minutes without heartbeats.
        $this->travelTo(now()->addMinutes(10));

        $failedLockKey = config('cache.prefix') . $failed->mutexName();

        $this->service = new class($failedLockKey) extends SchedulerLockService {
            public function __construct(
                private readonly string $failedLockKey
            ) {}

            protected function releaseLock(
                ?string $connection,
                string $table,
                string $key,
                string $owner
            ): int {
                if ($key === $this->failedLockKey) {
                    throw new \RuntimeException('Release denied');
                }

                return parent::releaseLock($connection, $table, $key, $owner);
            }
        };

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertSame(
            [Status::RELEASE_FAILED->value, Status::RELEASED->value],
            array_column($result['rows'], 'status')
        );

        $this->assertSame(2, $result['orphaned']);
        $this->assertSame(1, $result['released']);

        $this->assertNotNull($this->storedLock($failed));
        $this->assertNull($this->storedLock($released));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Failed to release orphaned mutex lock for [watchdog:failed]: Release denied'
            );
    }

    public function test_shared_mutex_names_are_not_counted_as_multiple_successful_releases(): void
    {
        $first = $this->scheduled('watchdog:work --quiet')
            ->createMutexNameUsing('shared-watchdog-lock');

        $this->scheduled('watchdog:work --verbose')
            ->createMutexNameUsing('shared-watchdog-lock');

        // Acquire the mutex 10 minutes before the watchdog scan.
        $this->travelTo(now()->subMinutes(10));
        $this->acquire($first);

        // Create the execution log immediately after acquiring the mutex.
        $this->log(
            ageSeconds: 0,
            options: ['quiet' => true],
            createdAt: now()->toDateTimeString()
        );

        // Simulate 10 minutes without a heartbeat.
        $this->travelTo(now()->addMinutes(10));

        $result = $this->service->scanSchedulerLocks(
            release: true,
            showAll: true
        );

        $this->assertSame(
            1,
            $result['released'],
            'A disappeared lock must not be counted as another successful release.'
        );

        $this->assertSame(
            [Status::RELEASED->value, Status::OK->value],
            array_column($result['rows'], 'status')
        );
    }

    public function test_on_one_server_election_lock_is_distinct_from_the_overlap_lock(): void
    {
        $event = $this->scheduled()->onOneServer();
        $electionMutex = new CacheSchedulingMutex($this->app->make('cache'));
        $this->assertTrue($electionMutex->create($event, now()));
        $electionKey = $this->lockKey($event).now()->format('Hi');
        $before = DB::connection(DatabaseConnection::APPLICATION)->table('cache_locks')->where('key', $electionKey)->first();

        $result = $this->service->scanSchedulerLocks(release: true, showAll: true);

        $this->assertSame(now()->timestamp + 3600, (int) $before->expiration);
        $this->assertSame(Status::OK->value, $result['rows'][0]['status']);
        $this->assertSame(0, $result['released']);
        $this->assertNotNull(DB::connection(DatabaseConnection::APPLICATION)->table('cache_locks')->where('key', $electionKey)->first());
    }

    public function test_lock_metadata_respects_a_configured_custom_database_lock_table(): void
    {
        Schema::connection(DatabaseConnection::APPLICATION)->rename('cache_locks', 'watchdog_custom_locks');
        config()->set('cache.stores.database.lock_table', 'watchdog_custom_locks');
        Cache::forgetDriver('database');
        $event = $this->scheduled();
        $this->acquire($event);

        $failure = null;
        $result = null;
        try {
            $result = $this->service->scanSchedulerLocks();
        } catch (QueryException $exception) {
            $failure = $exception;
        }

        $this->assertNull($failure, 'The scan must read the configured lock table: '.($failure?->getMessage() ?? ''));
        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
        $this->assertSame($this->lockKey($event), $result['rows'][0]['mutex_lock_key']);
    }

    public function test_lock_metadata_respects_a_separate_database_lock_connection(): void
    {
        Schema::connection('sqlite')->create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration');
        });
        config()->set('cache.stores.database.lock_connection', 'sqlite');
        Cache::forgetDriver('database');
        $event = $this->scheduled();
        $this->acquire($event);
        $this->assertNotNull(DB::connection('sqlite')->table('cache_locks')->where('key', $this->lockKey($event))->first());

        $result = $this->service->scanSchedulerLocks();

        $this->assertSame(Status::LOCKED->value, $result['rows'][0]['status']);
        $this->assertSame($this->lockKey($event), $result['rows'][0]['mutex_lock_key']);
    }

    public function test_database_read_errors_propagate_instead_of_reporting_no_locks(): void
    {
        $this->scheduled();
        Schema::connection(DatabaseConnection::APPLICATION)->drop('cache_locks');

        $this->expectException(QueryException::class);

        $this->service->scanSchedulerLocks();
    }

    public function test_a_stale_execution_does_not_release_a_newer_lock(): void
    {
        $event = $this->scheduled();

        // Execution A started an hour ago and stopped sending heartbeats.
        $this->log(
            ageSeconds: 600,
            createdAt: now()->subHour()->toDateTimeString()
        );

        // Execution A's original lock has expired.
        // Execution B has subsequently acquired a new lock.
        $this->acquire($event);

        $result = $this->service->scanSchedulerLocks(release: true);

        $this->assertNotNull(
            $this->storedLock($event),
            'A newer lock must not be released because an older execution is stale.'
        );

        $this->assertSame(0, $result['released']);
    }
}
