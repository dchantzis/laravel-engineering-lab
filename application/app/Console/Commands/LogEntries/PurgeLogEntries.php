<?php

namespace App\Console\Commands\LogEntries;

use App\Console\Commands\Command;
use App\Services\DatabaseLogServices\LogService;
use Carbon\Carbon;
use Exception;

abstract class PurgeLogEntries extends Command
{
    protected LogService $logService;

    protected int|null $daysDefault;

    protected Carbon $deleteEndDate;

    /**
     * PurgeLogEntries constructor.
     */
    public function __construct($logRepository, $daysDefault)
    {
        $this->logService = $logRepository;

        $this->daysDefault = $daysDefault;

        parent::__construct();
    }

    public function fulfill(): array
    {
        $days = $this->makeDaysParameter();

        $this->deleteEndDate = Carbon::now()->subDays($days)->endOfDay();

        $deleteResult = $this->massDeleteEntries($this->deleteEndDate);

        $this->line('');
        $this->line(sprintf(
                '<comment>Deleted all entries before</comment>: <info>%s</info> (now - <info>%s</info> days)', $this->deleteEndDate->format('Y-m-d H:i:s'), $days)
        );
        $this->line(sprintf(
            '<comment>Number of entries deleted</comment>: <info>%s</info>', $deleteResult
        ));

        return [
            'delete_end_date' => $this->deleteEndDate->format(dateFormat()),
            'entries_deleted' => $deleteResult,
        ];
    }

    protected function makeDaysParameter(): int
    {
        $days = (int) $this->option('days');

        do {
            if (! $days) {
                $days = (int) $this->daysDefault;
            }
            try {
                if ($days <= 1) {
                    throw new Exception(
                        sprintf('Invalid days value %s. Please enter an integer greater than 1', $days)
                    );
                }
                $retry = null;
            } catch (Exception $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $retry = $this->choice('Invalid days value. Retry?', [
                    'yes',
                    sprintf('use default days = %s', $this->daysDefault),
                ]);
                $days = ($retry !== 'yes') ? $this->daysDefault : (int) $this->ask('Specify days');
            }
        } while ($retry === 'yes');

        return $days;
    }

    protected function massDeleteEntries(Carbon $endDate): int
    {
        return $this->logService->massDeleteEntries($endDate);
    }
}
