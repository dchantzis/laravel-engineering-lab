<?php

namespace App\Console\Commands\LogEntries;

use App\Models\ConsoleCommandLog;
use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:view-console-command-log-entries')]
#[Description('Browse console command log entries')]
class ViewConsoleCommandLogEntries extends ViewLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:view-console-command-log-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Browse console command log entries';

    public function __construct()
    {
        parent::__construct(LogSystem::console());
    }

    public function defineCommandOptions(): void
    {
        $this->setCommandOptions(
            [
                self::VIEW_TYPE_DB_ENTRY_ID,
                self::VIEW_TYPE_MOST_RECENT,
                self::VIEW_TYPE_DATE_RANGE,
            ]
        );
    }

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
        );
    }

    protected function outputDetails(ConsoleCommandLog $entry): void
    {

        $this->info(sprintf(
            '<comment>ID</comment>: %s', $entry->id
        ));

        $this->info(sprintf(
            '<comment>Created At</comment>: %s', $entry->created_at
        ));

        $this->info(sprintf(
            '<comment>Updated At</comment>: %s', $entry->updated_at
        ));

        $this->info(sprintf(
            '<comment>Name</comment>: %s', $entry->name
        ));

        $this->comment('Parameters:');
        if (json_decode($entry->parameters)) {
            foreach (json_decode($entry->parameters, true) as $key => $value) {
                $this->info("\t".sprintf('<comment>%s</comment>: %s', $key, json_encode($value)));
            }
        } else {
            $this->info('-');
        }

        $this->info(sprintf(
            '<comment>Memory Usage Start</comment>: %s', $entry->memory_usage_start
        ));

        $this->info(sprintf(
            '<comment>Memory Usage End</comment>: %s', $entry->memory_usage_end
        ));

        $this->info(sprintf(
            '<comment>Success</comment>: %s', $entry->success
        ));

        $this->comment('Meta:');
        if (json_decode($entry->meta)) {
            foreach (json_decode($entry->meta, true) as $key => $value) {
                $this->info("\t".sprintf('<comment>%s</comment>: %s', $key, json_encode($value)));
            }
        } else {
            $this->info('-');
        }

        $this->line('');
    }
}
