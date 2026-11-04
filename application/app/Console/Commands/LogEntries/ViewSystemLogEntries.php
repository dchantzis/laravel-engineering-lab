<?php

namespace App\Console\Commands\LogEntries;

use App\Models\SystemLog;
use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Exception;

#[Signature('app:view-system-log-entries')]
#[Description('View entries from the system_logs table')]
class ViewSystemLogEntries extends ViewLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:view-system-log-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View entries from the system_logs table';

    /**
     * ViewSystemLogEntries constructor.
     */
    public function __construct()
    {
        parent::__construct(LogSystem::system());
    }

    public function defineCommandOptions(): void
    {
        $this->setCommandOptions(
            [
                self::VIEW_TYPE_DB_ENTRY_ID,
                self::VIEW_TYPE_MOST_RECENT,
                self::VIEW_TYPE_DATE_RANGE,
                self::VIEW_TYPE_IP_TRACE_ID,
                self::VIEW_TYPE_CONSOLE_COMMAND_ID,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        parent::handle();
    }

    /**
     * @throws Exception
     */
    public function fulfill(): array
    {
        $viewType = $this->choice(
            'Select view type',
            $this->commandOptions
        );

        switch ($viewType) {
            case self::VIEW_TYPE_DB_ENTRY_ID:
                $this->searchEntryById();

                break;
            case self::VIEW_TYPE_DATE_RANGE:
                $this->calculateDateDuration();
                $this->getEntries();

                break;
            case self::VIEW_TYPE_IP_TRACE_ID:
                $this->findIpTraceId();
                $this->getEntries();

                break;
            case self::VIEW_TYPE_CONSOLE_COMMAND_ID:
                $this->findConsoleCommandId();
                $this->getEntries();

                break;
            case self::VIEW_TYPE_MOST_RECENT:
            default:
                $this->getEntries();
                break;
        }
    }

    public function search(int $limit, int $offset)
    {
        return $this->logService->get(
            $limit,
            $offset,
            $this->startDate,
            $this->endDate,
            $this->ipTraceId,
            $this->consoleCommandLogId
        );
    }

    public function outputDetails(SystemLog $entry): void
    {
        $this->info(sprintf(
            '<comment>ID</comment>: %s',
            $entry->id
        ));

        $this->info(sprintf(
            '<comment>Created At</comment>: %s',
            $entry->created_at
        ));
        $this->info(sprintf(
            '<comment>Updated At</comment>: %s',
            $entry->updated_at
        ));

        $this->line(sprintf(
            '<comment>Level</comment>: %s %s',
            $entry->level,
            $entry->level_name
        ));

        $this->line(sprintf(
            '<comment>Message</comment>: %s',
            $entry->message
        ));

        if ($entry->context) {
            foreach ($entry->context as $key => $value) {
                $this->line("\t".sprintf(
                        '<comment>%s</comment>: %s', $key, json_encode($value)
                    ));
            }
        }

        $this->line(sprintf(
            '<comment>Channel</comment>: %s',
            $entry->channel
        ));

        if ($entry->consoleCommandLog) {
            $this->line(sprintf(
                '<comment>Console command log entry</comment>: ID: <info>%s</info>, Name: <info>%s</info>',
                $entry->consoleCommandLog->id,
                $entry->consoleCommandLog->name
            ));
        } else {
            $this->line(sprintf(
                '<comment>Console command log entry</comment>: %s', '-'
            ));
        }

        $this->line('');
    }
}
