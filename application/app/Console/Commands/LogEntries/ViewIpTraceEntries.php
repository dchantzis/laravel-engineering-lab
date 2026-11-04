<?php

namespace App\Console\Commands\LogEntries;

use App\Models\IpTrace;
use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:view-ip-trace-entries')]
#[Description('View entries from the ip_trace table')]
class ViewIpTraceEntries extends ViewLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:view-ip-trace-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View entries from the ip_trace table';

    /**
     * ViewIpTraceEntries constructor.
     */
    public function __construct()
    {
        parent::__construct(LogSystem::ipTrace());
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

    public function outputDetails(IpTrace $entry): void
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

        $this->line(sprintf(
            '<comment>IP Address</comment>: %s', $entry->ip_address
        ));

        $this->line(sprintf(
            '<comment>Request Method</comment>: %s', $entry->request_method
        ));

        $this->line(sprintf(
            '<comment>Route</comment>: %s', $entry->route
        ));

        $this->comment('Request Parameters');
        if ($entry->request_parameters) {
            foreach ($entry->request_parameters as $key => $value) {
                $this->line("\t".sprintf(
                        '<comment>%s</comment>: <info>%s</info>', $key, json_encode($value)
                    ));
            }
        }

        $this->comment('Request Body');
        if ($entry->request_body) {
            foreach ($entry->request_body as $key => $value) {
                $this->line("\t".sprintf(
                        '<comment>%s</comment>: <info>%s</info>', $key, json_encode($value)
                    ));
            }
        }

        $this->comment('Request Header');
        if ($entry->request_header) {
            foreach ($entry->request_header as $key => $value) {
                $this->line("\t".sprintf(
                        '<comment>%s</comment>: <info>%s</info>', $key, json_encode($value)
                    ));
            }
        }

        $this->line(sprintf(
            '<comment>Host</comment>: %s', $entry->host
        ));

        $this->line('');
    }
}
