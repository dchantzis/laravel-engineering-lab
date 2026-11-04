<?php

namespace App\Console\Commands;

use App\Models\ConsoleCommandLog;
use App\Services\LogSystem;
use App\Services\Notify;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Error;
use Exception;
use Illuminate\Console\Command as BaseCommand;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class Command extends BaseCommand implements Isolatable
{
    private int $heartbeatIntervalSeconds;

    protected Carbon $now;

    protected ?ConsoleCommandLog $consoleLogEntry = null;

    protected ?Carbon $startDate;

    protected ?Carbon $endDate;

    protected int $memoryUsageStart;

    protected int $memoryUsageEnd;

    public function __construct()
    {
        $this->now = Carbon::now();

        $this->startDate = Carbon::now()->startOfDay();

        $this->endDate = Carbon::now()->endOfDay();

        $this->memoryUsageStart = memory_get_usage(true);

        parent::__construct();
    }

    public function handle(): void
    {
        try {
            $this->consoleLogEntry = LogSystem::console()->start(
                name: $this->getName(),
                memoryUsageStart: $this->memoryUsageStart,
                parameters: [
                    'arguments' => $this->arguments(),
                    'options' => $this->options(),
                ]
            );
        } catch (Throwable $exception) {
            $this->logWarning(
                sprintf(
                    'Failed to create console command log for [%s]: %s',
                    $this->getName(),
                    $exception->getMessage()
                )
            );
        }

        try {
            $this->startHeartbeat();

            $executionResult = $this->fulfill();
            $this->memoryUsageEnd = memory_get_peak_usage(true);

            if ($this->consoleLogEntry !== null) {
                try {
                    LogSystem::console()->end(
                        $this->consoleLogEntry->id,
                        $this->memoryUsageEnd,
                        true,
                        $executionResult
                    );
                } catch (Throwable $exception) {
                    $this->logWarning(
                        sprintf(
                            'Failed to create console command log for [%s]: %s',
                            $this->getName(),
                            $exception->getMessage()
                        )
                    );
                }
            }
        } catch (Exception|Error $exception) {
            $this->outputException($exception);
            $this->memoryUsageEnd = memory_get_peak_usage(true);

            if ($this->consoleLogEntry !== null) {
                try {
                    LogSystem::console()->end(
                        id: $this->consoleLogEntry->id,
                        memoryUsageEnd: $this->memoryUsageEnd,
                        success: false,
                        meta: [
                            'code' => $exception->getCode(),
                            'exception' => $exception->getMessage(),
                        ]
                    );
                } catch (Throwable $loggingException) {
                    $this->logWarning(
                        sprintf(
                            'Failed to record console command failure for [%s]: %s',
                            $this->getName(),
                            $loggingException->getMessage()
                        )
                    );
                }
            }

            $this->printStatistics();

            $this->logWarning(
                sprintf('Command [%s]: %s.', $this->getName(), $exception->getMessage()),
                [
                    'exception' => sprintf('[Code:%s] %s.', $exception->getCode(), $exception->getMessage()),
                    'file' => sprintf('%s:%s.', $exception->getFile(), $exception->getLine()),
                ]
            );

            throw $exception;
        } finally {
            $this->stopHeartbeat();
        }

        $this->printStatistics();

        $this->line('');
        $this->info('Exiting...');

        $this->sendToSlack($executionResult);
    }

    /**
     * @return mixed
     */
    abstract public function fulfill(): array;

    private function printStatistics(): void {
        $this->line('');

        if ($this->consoleLogEntry !== null) {
            $this->info(
                sprintf('<comment>Command ID:</comment> %s', $this->consoleLogEntry->id)
            );
        }

        $this->info(
            sprintf('<comment>Memory Usage Start:</comment> %s', formatBytesToHuman($this->memoryUsageStart))
        );

        $this->info(
            sprintf('<comment>Memory Usage End:</comment> %s', formatBytesToHuman($this->memoryUsageEnd))
        );

        $this->info(
            sprintf(
                '<comment>Total Memory Usage (Start VS Peak):</comment> %s',
                formatBytesToHuman(
                    $this->memoryUsageEnd - $this->memoryUsageStart
                )
            )
        );

        $this->info(sprintf(
            '<comment>Execution started at:</comment> %s', $this->now->format(dateFormat())
        ));

        $executionEndNow = Carbon::now();
        $this->info(sprintf(
            '<comment>Execution end at:</comment> %s', $executionEndNow->format(dateFormat())
        ));
        $this->info(
            sprintf('<comment>Execution duration:</comment> %s (H:i:s)', gmdate('H:i:s', $this->now->diffInSeconds($executionEndNow)))
        );
    }

    public function getConsoleLogEntry(): ?ConsoleCommandLog
    {
        try {
            return $this->consoleLogEntry?->fresh();
        } catch (Throwable $loggingException) {
            $this->logWarning(
                sprintf(
                    'Failed to retrieve console command log entry for [%s]: %s',
                    $this->getName(),
                    $loggingException->getMessage()
                )
            );
        }

        return null;
    }

    public function getLastSuccessfulCommand(): ?ConsoleCommandLog
    {
        /** @var ConsoleCommandLog $lastSuccessfulCommand */
        $lastSuccessfulCommand = $this->getLastSuccessfulCommandQuery()
            ->orderBy('id', 'desc')
            ->first();

        if ($lastSuccessfulCommand) {
            $this->info(sprintf('Last successful command run on <comment>%s</comment>', $lastSuccessfulCommand->created_at));
        }

        return $lastSuccessfulCommand;
    }

    public function getLastSuccessfulCommandQuery(): Builder
    {
        return ConsoleCommandLog::whereName($this->getName())
            ->where('success', true);
    }

    protected function sendToSlack(
        array $executionResult
    ): void {
        try {
            Notify::scheduledCommandLogChannel(
                message: $this->getName(),
                content: array_merge(
                    [
                        'console_command_log_id' => $this->consoleLogEntry?->id,
                        'description' => $this->getDescription(),
                    ],
                    array_merge(
                        $executionResult,
                        [
                            'command_duration' => sprintf('Start [%s], End [%s]',
                                $this->now->format(dateFormat()),
                                Carbon::now()->format(dateFormat())
                            ),
                            'memory_usage' => sprintf(
                                'Start [%s], End [%s]',
                                formatBytesToHuman($this->memoryUsageStart),
                                formatBytesToHuman($this->memoryUsageEnd)
                            ),
                        ]
                    ),
                ),
                writeInLog: false
            );
        } catch (Throwable $notificationException) {
            $this->logWarning(
                sprintf(
                    'Failed to send console command notification for [%s]: %s',
                    $this->getName(),
                    $notificationException->getMessage()
                )
            );
        }
    }

    public function isolatableId(): string
    {
        return '';
    }

    public function isolationLockExpiresAt(): CarbonInterval
    {
        return CarbonInterval::hour(); // This value is the existing default.
    }

    /**
     * Start a periodic heartbeat that touches the console log entry's updated_at column.
     *
     * This allows the watchdog command to detect orphaned locks by checking
     * whether updated_at has become stale (i.e. the pod died and stopped sending heartbeats).
     *
     * @return void
     */
    private function startHeartbeat(): void
    {
        try {
            if (! extension_loaded('pcntl') || $this->consoleLogEntry === null) {
                return;
            }

            $this->heartbeatIntervalSeconds = (int) config('engineering.console_command_execution_heartbeat_interval_seconds', 30);

            pcntl_async_signals(true);

            pcntl_signal(SIGALRM, function () {
                try {
                    $this->consoleLogEntry->touch();
                } catch (Throwable $e) {
                    // Don't let heartbeat failures interrupt command execution
                } finally {
                    pcntl_alarm($this->heartbeatIntervalSeconds);
                }
            });

            pcntl_alarm($this->heartbeatIntervalSeconds);
        } catch (Throwable $heartbeatException) {
            $this->logWarning(
                sprintf(
                    'Failed to start heartbeat for [%s]: %s',
                    $this->getName(),
                    $heartbeatException->getMessage()
                )
            );
        }
    }

    /**
     * Stop the heartbeat signal.
     *
     * @return void
     */
    private function stopHeartbeat(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        try {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_IGN);
        } catch (Throwable $exception) {
            $this->logWarning(
                sprintf(
                    'Failed to stop heartbeat for [%s]: %s',
                    $this->getName(),
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::execute($input, $output);
        } catch (DeadlockException $exception) {
            // return parent::execute($input, $output); Can be used in individual commands if required
            $this->outputException($exception);

            $this->logWarning(
                sprintf('Command [%s] deadlocked: %s.', $this->name, $exception->getMessage()),
                [
                    'exception' => sprintf('[Code:%s] %s.', $exception->getCode(), $exception->getMessage()),
                    'file' => sprintf('%s:%s.', $exception->getFile(), $exception->getLine()),
                ]
            );
        }

        return 1;
    }

    protected function setDateDurationWithDurationUpperLimit(): void {
        $startDate = $this->ask('Start Date and Time [YYYY-MM-DD HH:MM:SS]');
        $endDate = $this->ask('End Date and Time [YYYY-MM-DD HH:MM:SS]');

        $startDate = str_replace('/', '-', $startDate);
        $endDate = str_replace('/', '-', $endDate);

        try {
            $this->startDate = new Carbon($startDate);
        } catch (Exception $exception) {
            $this->error('Invalid start date format. Please use YYYY-MM-DD HH:MM:SS format.');
            throw $exception;
        }

        try {
            $this->endDate = new Carbon($endDate);
        } catch (Exception $exception) {
            $this->error('Invalid end date format. Please use YYYY-MM-DD HH:MM:SS format.');
            throw $exception;
        }

        if ($this->startDate->isAfter($this->endDate)) {
            $this->error('Start date must be before or equal to end date.');
            throw new Exception('Invalid date range: start date is after end date.');
        }

        if ($this->startDate->format('H:i:s') === '00:00:00') {
            $this->startDate->startOfDay();
        }

        if ($this->endDate->format('H:i:s') === '00:00:00') {
            $this->endDate->endOfDay();
        }
    }

    protected function setDateDurationDefaults(): void
    {
        $this->startDate = Carbon::now()->startOfDay();
        $this->endDate = Carbon::now();

        if ($lastSuccessfulCommandExecution = $this->getLastSuccessfulCommand()) {
            $lastSuccessfulCommandExecutionStartDate = $lastSuccessfulCommandExecution->created_at;
            $fallbackAllowedStartDate = Carbon::now()
                ->subDays(7)
                ->startOfDay();

            $this->startDate = $lastSuccessfulCommandExecutionStartDate->isAfter($fallbackAllowedStartDate)
                ? $lastSuccessfulCommandExecutionStartDate
                : $fallbackAllowedStartDate;
        }
    }

    protected function calculateDateDurationWithDurationUpperLimit(
        int $durationHoursUpperLimit = 0,
        int $startDateSubMinutes = 0,
    ): void {
        if ($this->option('set-duration')) {
            $this->setDateDurationWithDurationUpperLimit();
        } else {
            $this->setDateDurationDefaults();
        }

        if ($durationHoursUpperLimit
            && $this->startDate->diffInHours($this->endDate) > $durationHoursUpperLimit) {
            $this->warn(sprintf(
                'The difference between the specified duration is more than [%d] hour(s). The end date is adjusted accordingly.',
                $durationHoursUpperLimit
            ));

            $this->endDate = clone $this->startDate;
            $this->endDate->addHours($durationHoursUpperLimit);
        }

        if ($startDateSubMinutes) {
            $this->comment(sprintf(
                'Setting start date [%d] minute(s) in the past.',
                $startDateSubMinutes
            ));
            $this->startDate->subMinutes($startDateSubMinutes);
        }

        $this->comment(sprintf(
            '   Start Date: <info>[%s]</info>',
            $this->startDate->format(dateFormat())
        ));
        $this->comment(sprintf(
            '   End Date: <info>[%s]</info>',
            $this->endDate->format(dateFormat())
        ));
    }

    public function outputException(
        Exception|Error|Throwable $exception
    ): void
    {
        $this->line('');

        $this->error('Exception:');

        $this->error(sprintf(
            '<info>Exception:</info> [Code: %s] %s.',
            $exception->getCode(),
            $exception->getMessage()
        ));

        $this->error(sprintf(
            '<info>File and Line:</info> %s:%s',
            $exception->getFile(),
            $exception->getLine()
        ));
    }

    private function logWarning(string $message, array $context = []): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            // Logging must never interfere with command execution.
        }
    }
}
