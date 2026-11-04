<?php

namespace App\Services\Admin;

use App\Enums\ScheduledCommandLockStatus;
use App\Models\ConsoleCommandLog;
use App\Repositories\ConsoleCommandLogRepository;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Application as SymfonyApplication;

class SchedulerLockService
{
    public function scanSchedulerLocks(
        bool $release = false,
        bool $showAll = false,
        ?int $thresholdMinutes = null
    ): array {
        $thresholdMinutes ??= (int) config('engineering.scheduler_commands_scan_orphan_threshold_minutes', 5);

        if ($thresholdMinutes < 1) {
            throw new \InvalidArgumentException(
                'The orphan threshold must be a positive integer.'
            );
        }

        $cachePrefix = (string) config('cache.prefix');

        /** @var Schedule $schedule */
        $schedule = App::make(Schedule::class);
        $events = collect($schedule->events());

        if ($events->isEmpty()) {
            return [
                'total' => 0,
                'orphaned' => 0,
                'released' => 0,
                'rows' => [],
            ];
        }

        $cacheStore = config('cache.default');

        $connection = config("cache.stores.{$cacheStore}.lock_connection")
            ?? config("cache.stores.{$cacheStore}.connection");

        $table = config("cache.stores.{$cacheStore}.lock_table", 'cache_locks');

        $cacheLocks = DB::connection($connection)
            ->table($table)
            ->get()
            ->keyBy('key');

        $currentlyRunning = ConsoleCommandLogRepository::getCurrentlyRunning();

        $orphanedCount = 0;
        $releasedCount = 0;
        $now = Carbon::now();

        $rows = $events->map(function ($event) use (
            $cachePrefix,
            $cacheLocks,
            $connection,
            $table,
            $currentlyRunning,
            $release,
            $thresholdMinutes,
            $now,
            &$orphanedCount,
            &$releasedCount
        ) {
            $normalizedCommand = Event::normalizeCommand($event->command);
            $commandName = $this->extractCommandName($normalizedCommand);
            $commandInput = $this->parseCommandInput($normalizedCommand, $commandName);

            $hasMutex = $event->mutex->exists($event);

            $mutexLockKey = $cachePrefix . $event->mutexName();
            $cacheLock = $cacheLocks->get($mutexLockKey);

            $runningLog = $commandInput === null
                ? null
                : $this->findRunningLogEntry($currentlyRunning, $commandName, $commandInput);

            // Orphaned detection:
            // 1. Mutex lock exists (command appears to be running)
            // 2. A currently running log entry exists for this command
            // 3. The log entry's updated_at is stale — the heartbeat stopped updating it,
            //    meaning the process (pod) died and can no longer release the lock
            $isIncomplete = $hasMutex && $runningLog;

            $stalenessMinutes = $isIncomplete
                ? (int) $runningLog->updated_at->diffInMinutes($now)
                : 0;

            $isOrphaned = $isIncomplete && $stalenessMinutes >= $thresholdMinutes;

            if ($isOrphaned) {
                $orphanedCount++;
                $status = ScheduledCommandLockStatus::ORPHANED;

                if ($release) {
                    try {
                        // Recheck the heartbeat before releasing the mutex.
                        $latestHeartbeat = DB::connection($runningLog->getConnectionName())
                            ->table($runningLog->getTable())
                            ->where('id', $runningLog->id)
                            ->value('updated_at');

                        $stillStale = $latestHeartbeat !== null
                            && Carbon::parse($latestHeartbeat)->diffInMinutes(Carbon::now()) >= $thresholdMinutes;

                        $lockBelongsToExecution = false;

                        if ($cacheLock && $runningLog->created_at) {
                            $lockAcquiredAt = Carbon::createFromTimestamp(
                                $cacheLock->expiration - ($event->expiresAt * 60)
                            );

                            $lockBelongsToExecution = $lockAcquiredAt->lessThanOrEqualTo(
                                $runningLog->created_at
                            );
                        }

                        if ($stillStale && $cacheLock && $lockBelongsToExecution) {
                            $deleted = $this->releaseLock(
                                $connection,
                                $table,
                                $mutexLockKey,
                                $cacheLock->owner
                            );

                            if ($deleted === 1) {
                                $releasedCount++;
                                $status = ScheduledCommandLockStatus::RELEASED;
                            }
                        }
                    } catch (Throwable $e) {
                        $status = ScheduledCommandLockStatus::RELEASE_FAILED;

                        Log::warning(sprintf(
                            'Failed to release orphaned mutex lock for [%s]: %s',
                            $commandName,
                            $e->getMessage()
                        ));
                    }
                }
            } elseif ($isIncomplete) {
                $status = ScheduledCommandLockStatus::LOCKED_ACTIVE;
            } elseif ($hasMutex) {
                $status = ScheduledCommandLockStatus::LOCKED;
            } else {
                $status = ScheduledCommandLockStatus::OK;
            }

            $lockExpires = $cacheLock
                ? Carbon::createFromTimestamp($cacheLock->expiration)->format(dateFormat())
                : '-';

            return [
                'command' => $normalizedCommand,
                'expression' => $event->expression,
                'status' => $status->value,
                'mutex_lock_key' => $cacheLock ? $mutexLockKey : '-',
                'lock_expires' => $lockExpires,
                'last_heartbeat' => $runningLog?->updated_at?->format('Y-m-d H:i:s') ?? '-',
                'stale_minutes' => $isIncomplete ? $stalenessMinutes : '-',
                'host' => $runningLog?->executed_on_host ?? '-',
                'log_id' => $runningLog?->id ?? '-',
                '_has_mutex' => $hasMutex,
            ];
        });

        if (! $showAll) {
            $rows = $rows->filter(fn ($row) => $row['_has_mutex']);
        }

        // Remove internal flag before output
        $displayRows = $rows
            ->map(fn ($row) => collect($row)->except('_has_mutex')->all())
            ->values();

        return [
            'total' => $displayRows->count(),
            'orphaned' => $orphanedCount,
            'released' => $releasedCount,
            'rows' => $displayRows->toArray(),
        ];
    }

    /**
     * Extract the artisan command name from a normalized command string.
     *
     * Normalized format: "php artisan command:name --options arg1 arg2"
     */
    private function extractCommandName(string $command): string
    {
        $withoutPrefix = trim(str_replace('php artisan', '', $command));

        // First token is the command name
        $name = explode(' ', $withoutPrefix, 2)[0];

        return $name ?: $withoutPrefix;
    }

    /**
     * Parse a scheduled Artisan command using its registered definition.
     */
    private function parseCommandInput(string $command, string $commandName): ?array
    {
        $artisanCommand = Artisan::all()[$commandName] ?? null;

        if ($artisanCommand === null) {
            return null;
        }

        $position = strpos($command, $commandName);

        $parameters = trim(substr(
            $command,
            $position + strlen($commandName)
        ));

        $definition = clone $artisanCommand->getDefinition();

        $definition->addOptions(
            (new SymfonyApplication())->getDefinition()->getOptions()
        );

        $input = new StringInput($parameters);
        $input->bind($definition);

        return [
            'arguments' => array_filter(
                $input->getArguments(),
                fn ($value) => $value !== null
            ),
            'options' => array_filter(
                $input->getOptions(),
                fn ($value) => $value !== null && $value !== false
            ),
        ];
    }

    /**
     * Find the newest running execution matching the scheduled command.
     */
    private function findRunningLogEntry(
        Collection $currentlyRunning,
        string $commandName,
        array $commandInput
    ): ?ConsoleCommandLog {
        return $currentlyRunning
            ->where('name', $commandName)
            ->filter(function (ConsoleCommandLog $log) use ($commandInput) {
                $parameters = json_decode($log->parameters, true);

                if (! is_array($parameters)) {
                    return false;
                }

                $loggedArguments = $parameters['arguments'] ?? [];
                $loggedOptions = $parameters['options'] ?? [];

                foreach ($commandInput['arguments'] as $name => $value) {
                    if (! array_key_exists($name, $loggedArguments)
                        || $loggedArguments[$name] != $value) {
                        return false;
                    }
                }

                foreach ($commandInput['options'] as $name => $value) {
                    if (! array_key_exists($name, $loggedOptions)
                        || $loggedOptions[$name] != $value) {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc('id')
            ->first();
    }

    protected function releaseLock(
        ?string $connection,
        string $table,
        string $key,
        string $owner
    ): int {
        return DB::connection($connection)
            ->table($table)
            ->where('key', $key)
            ->where('owner', $owner)
            ->delete();
    }
}
