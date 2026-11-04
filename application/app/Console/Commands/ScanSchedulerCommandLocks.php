<?php

namespace App\Console\Commands;

use App\Services\Admin\SchedulerLockService;

class ScanSchedulerCommandLocks extends Command
{
    protected $signature = 'app:scan-scheduler-command-locks
        {--release : Release orphaned locks automatically}
        {--all : Show all scheduled events, not just locked ones}
        {--threshold= : Minutes of heartbeat staleness before flagging as orphaned (default: 5)}';

    protected $description = 'Scan scheduled commands for orphaned mutex locks caused by unexpected pod termination.';

    public function __construct(protected SchedulerLockService $schedulerLockService)
    {
        parent::__construct();
    }

    public function fulfill(): array
    {
        $thresholdOption = $this->option('threshold');

        $threshold = $thresholdOption
            ?? config('engineering.scheduler_commands_scan_orphan_threshold_minutes', 5);

        if (
            filter_var($threshold, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]) === false
        ) {
            throw new \InvalidArgumentException(
                'The orphan threshold must be a positive integer.'
            );
        }

        $thresholdMinutes = (int) $threshold;

        $result = $this->schedulerLockService->scanSchedulerLocks(
            release: (bool) $this->option('release'),
            showAll: (bool) $this->option('all'),
            thresholdMinutes: $thresholdMinutes,
        );

        $this->components->info(
            sprintf('Orphan threshold: %d minute(s) of heartbeat staleness.', $thresholdMinutes)
        );

        if (empty($result['rows'])) {
            $this->components->info('No active mutex locks found on scheduled commands.');

            return $result;
        }

        $this->table(
            ['Command', 'Expression', 'Status', 'Mutex Lock', 'Lock Expires', 'Last Heartbeat', 'Stale (min)', 'Host', 'Log ID'],
            $result['rows']
        );

        $this->line('');
        $this->components->info(
            sprintf('Total: %d | Orphaned: %d | Released: %d', $result['total'], $result['orphaned'], $result['released'])
        );

        return $result;
    }
}
