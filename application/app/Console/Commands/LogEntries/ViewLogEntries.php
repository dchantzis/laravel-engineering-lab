<?php

namespace App\Console\Commands\LogEntries;

use App\Console\Commands\Command;
use App\Services\DatabaseLogServices\ApiAuditLogService;
use App\Services\DatabaseLogServices\ConsoleCommandLogService;
use App\Services\DatabaseLogServices\IpTraceService;
use App\Services\DatabaseLogServices\SystemLogService;
use App\Services\LogSystem;
use Carbon\Carbon;
use Exception;
use TypeError;

abstract class ViewLogEntries extends Command
{
    const string VIEW_TYPE_DATE_RANGE = 'Date range';

    const string VIEW_TYPE_MOST_RECENT = 'Most recent';

    const string VIEW_TYPE_DB_ENTRY_ID = 'Database entry ID';

    const string VIEW_TYPE_OAUTH_CLIENT = 'Oauth Client ID';

    const string VIEW_TYPE_USER_ID = 'User ID';

    const string VIEW_REQUEST_UUID = 'Request UUID';

    const string VIEW_TYPE_CONSOLE_COMMAND_ID = 'Console Command Log ID';

    const string VIEW_TYPE_IP_TRACE_ID = 'IP Trace ID';

    const int NUMBER_OF_RESULTS_DEFAULT = 10;

    const int NUMBER_OF_RESULTS_MAXIMUM = 20;

    protected ConsoleCommandLogService|SystemLogService|IpTraceService|ApiAuditLogService $logService;

    /**
     * @var string[]
     */
    protected array $commandOptions;

    protected ?Carbon $startDate;

    protected ?Carbon $endDate;

    protected ?int $ipTraceId;

    protected ?int $consoleCommandLogId;

    protected ?string $oauthClientId;

    protected ?int $userId;

    protected ?string $uuid;

    public function __construct($logRepository)
    {
        $this->logService = $logRepository;

        $this->startDate = null;
        $this->endDate = null;
        $this->ipTraceId = null;
        $this->consoleCommandLogId = null;

        $this->defineCommandOptions();

        parent::__construct();
    }

    abstract public function defineCommandOptions(): void;

    abstract public function search(int $limit, int $offset);

    public function setCommandOptions(array $commandOptions): void
    {
        $this->commandOptions = $commandOptions;
    }

    public function findIpTraceId(): void
    {
        do {

            $executeAgain = false;

            $inputId = $this->ask('IP Trace ID');

            try {

                $selectionDBEntry = LogSystem::ipTrace()->find($inputId);
                if (! $selectionDBEntry) {
                    throw new Exception(sprintf(
                        'Unable to find database entry for id [%s]',
                        $inputId
                    ));
                }

                $this->ipTraceId = $selectionDBEntry->id;
            } catch (Exception $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $executeAgain = $this->confirm('Execute again?');
            }

        } while ($executeAgain);
    }

    protected function findConsoleCommandId(): void
    {
        do {
            $executeAgain = false;

            $inputId = $this->ask('Console Command Log ID');

            try {

                $selectionDBEntry = LogSystem::console()->find($inputId);
                if (! $selectionDBEntry) {
                    throw new Exception(sprintf('Unable to find database entry for id [%s]', $inputId));
                }

                $this->consoleCommandLogId = $selectionDBEntry->id;
            } catch (Exception $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $executeAgain = $this->confirm('Execute again?');
            }

        } while ($executeAgain);
    }

    public function searchEntryById(): void
    {
        do {

            $executeAgain = false;

            $entryId = (int) $this->ask('Database entry ID');

            try {

                $entry = $this->logService->find($entryId);

                if (! $entry) {
                    throw new Exception(sprintf(
                        'Unable to find database entry for id [%s]',
                        $entryId
                    ));
                }

                $this->outputDetails($entry);

            } catch (Exception|TypeError $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $executeAgain = $this->confirm('Execute again?');
            }

        } while ($executeAgain);
    }

    protected function calculateDateDuration(): void
    {
        $startDate = $this->ask('Start Date [YYYY-MM-DD HH:MM:SS]');
        $endDate = $this->ask('End Date [YYYY-MM-DD HH:MM:SS]');

        $startDate = str_replace('/', '-', $startDate);
        $endDate = str_replace('/', '-', $endDate);

        $this->startDate = new Carbon($startDate);

        if ($this->startDate->format('H:i:s') === '00:00:00') {
            $this->startDate->startOfDay();
        }

        $this->endDate = new Carbon($endDate);

        if ($this->endDate->format('H:i:s') === '00:00:00') {
            $this->endDate->endOfDay();
        }

        if ($this->startDate->diffInDays($this->endDate) < 1) {
            $this->endDate = clone $this->startDate;
            $this->endDate->endOfDay();
        }
    }

    protected function getEntries(): void
    {
        $offset = 0;
        $page = 1;

        $numberOfResults = $this->selectNumberOfResults();

        do {

            $results = $this->search($numberOfResults, $offset);

            $this->line(sprintf('<comment>%s</comment> results', $results->count()));
            $this->line('');

            if ($results->count() == 0) {
                $this->comment('End of results.');
                $executeAgain = false;

                continue;
            }

            foreach ($results as $entry) {
                $this->outputDetails($entry);
                $this->line('');
            }

            $executeAgain = $this->confirm(sprintf('View next %s?', $numberOfResults), true);

            $page++;

            $offset = ($page * $numberOfResults) - $numberOfResults;

        } while ($executeAgain);
    }

    protected function selectNumberOfResults(): int
    {
        $numberOfResults = (int) $this->ask(
            sprintf('Number of entries to view (Desc). Maximum %s', self::NUMBER_OF_RESULTS_MAXIMUM),
            self::NUMBER_OF_RESULTS_DEFAULT
        );

        if ($numberOfResults < 0 || $numberOfResults > self::NUMBER_OF_RESULTS_MAXIMUM) {
            $numberOfResults = self::NUMBER_OF_RESULTS_DEFAULT;
        }

        return $numberOfResults;
    }
}
