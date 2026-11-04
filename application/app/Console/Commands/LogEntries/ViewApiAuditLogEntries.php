<?php

namespace App\Console\Commands\LogEntries;

use App\Models\ApiAuditLog;
use App\Models\User;
use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Laravel\Passport\Client;

#[Signature('app:view-api-audit-log-entries')]
#[Description('View entries from the api_audit_logs table')]
class ViewApiAuditLogEntries extends ViewLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:view-api-audit-log-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View entries from the api_audit_logs table';

    protected ?string $oauthClientId;

    protected ?int $userId;

    protected ?string $requestUuid;

    /**
     * ViewSystemLogEntries constructor.
     */
    public function __construct()
    {
        $this->oauthClientId = null;
        $this->userId = null;
        $this->requestUuid = null;

        parent::__construct(LogSystem::api());
    }

    public function defineCommandOptions(): void
    {
        $this->setCommandOptions(
            [
                self::VIEW_TYPE_DB_ENTRY_ID,
                self::VIEW_TYPE_MOST_RECENT,
                self::VIEW_TYPE_DATE_RANGE,
                self::VIEW_TYPE_IP_TRACE_ID,
                self::VIEW_TYPE_OAUTH_CLIENT,
                self::VIEW_TYPE_USER_ID,
                self::VIEW_REQUEST_UUID,
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

            case self::VIEW_TYPE_OAUTH_CLIENT:
                $this->findOauthClient();
                $this->getEntries();

                break;

            case self::VIEW_TYPE_USER_ID:
                $this->findUser();
                $this->getEntries();

                break;

            case self::VIEW_REQUEST_UUID:
                $this->findRequestUuid();
                $this->getEntries();

                break;

            case self::VIEW_TYPE_MOST_RECENT:
            default:
                $this->getEntries();
                break;
        }

        return [];
    }

    protected function outputDetails(ApiAuditLog $entry): void
    {
        $this->line(sprintf(
            '<comment>ID</comment>: <info>%s</info>',
            $entry->id
        ));

        $this->line(sprintf(
            '<comment>Created At</comment>: <info>%s</info>',
            $entry->created_at
        ));
        $this->line(sprintf(
            '<comment>Updated At</comment>: <info>%s</info>',
            $entry->updated_at
        ));

        if ($entry->client) {
            if ($entry->client) {
                $this->line(sprintf(
                    '<comment>Client ID</comment>: <info>%s</info>, <comment>Name</comment>: <info>%s</info>',
                    $entry->client->id,
                    $entry->client->name
                ));
            }
        } else {
            $this->line(sprintf(
                '<comment>Client</comment>: %s',
                '-'
            ));
        }

        if ($entry->user) {
            if ($entry->user) {
                $this->line(sprintf(
                    '<comment>User ID</comment>: <info>%s</info>, <comment>Email</comment>: <info>%s</info>',
                    $entry->user->id,
                    $entry->user->email
                ));
            }
        } else {
            $this->line(sprintf(
                '<comment>User</comment>: %s',
                '-'
            ));
        }

        if ($entry->resource) {
            $this->line(sprintf(
                '<comment>Resource</comment>: <info>%s</info>',
                $entry->resource,
            ));
        } else {
            $this->line(sprintf(
                '<comment>Resource</comment>: %s',
                '-'
            ));
        }

        if ($entry->request_uuid) {
            $this->line(sprintf(
                '<comment>Request UUID:</comment> <info>%s</info>',
                $entry->request_uuid
            ));
        } else {
            $this->line(sprintf(
                '<comment>Request UUID</comment>: %s',
                '-'
            ));
        }

        if ($entry->ipTrace) {
            $this->line(sprintf(
                '<comment>IP Trace ID</comment>: <info>%s</info>',
                $entry->ipTrace->id
            ));
        } else {
            $this->line(sprintf(
                '<comment>IP Trace</comment>: %s',
                '-'
            ));
        }

        $this->line(sprintf(
            '<comment>Type</comment>: <info>%s</info>',
            $entry->type
        ));

        $this->line('<comment>Content</comment>:');
        foreach (json_decode($entry->content, true) as $key => $value) {
            $this->line("\t".sprintf(
                    '<comment>%s</comment>: <info>%s</info>', $key, json_encode($value)
                ));
        }

        $this->line('<comment>Meta</comment>:');

        foreach ($entry->meta as $key => $value) {
            $this->info("\t".sprintf(
                    '<comment>%s</comment>: <info>%s</info>', $key, json_encode($value)
                ));
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
            $this->oauthClientId,
            $this->userId,
            $this->requestUuid,
        );
    }

    private function findOauthClient()
    {
        do {

            $executeAgain = false;

            $oauthClientName = Client::orderBy('id', 'ASC')
                ->get('name')
                ->pluck('name')
                ->toArray();

            $selection = $this->choice('Select Oauth Client', $oauthClientName);

            try {

                $selectionDBEntry = Client::whereName($selection)->first();

                $this->oauthClientId = $selectionDBEntry->id;

            } catch (Exception $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $executeAgain = $this->confirm('Execute again?');
            }

        } while ($executeAgain);
    }

    private function findUser()
    {
        do {

            $executeAgain = false;

            $userResult = User::orderBy('id', 'ASC')
                ->select([
                    'id',
                    'email',
                ])
                ->get()
                ->map(function ($item, $key) {
                    return sprintf('id: %d - email: %s', $item->id, $item->email);
                })->toArray();

            $selection = $this->choice('Select User', $userResult);

            try {

                $userEmail = explode('email:', $selection);

                $selectionDBEntry = User::whereEmail(
                    trim($userEmail[1])
                )->first();

                $this->userId = $selectionDBEntry->id;

            } catch (Exception $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $executeAgain = $this->confirm('Execute again?');
            }

        } while ($executeAgain);
    }

    private function findRequestUuid()
    {
        do {

            $executeAgain = false;

            $inputId = $this->ask('Request UUID');

            try {

                $selectionDBEntry = LogSystem::api()->countByRequestUuid($inputId);
                if (! $selectionDBEntry) {
                    throw new Exception(sprintf(
                        'Unable to find database entry for request_uuid [%s]',
                        $inputId
                    ));
                }

                $this->requestUuid = $inputId;
            } catch (Exception $exception) {
                $this->line('');
                $this->warn($exception->getMessage());

                $executeAgain = $this->confirm('Execute again?');
            }

        } while ($executeAgain);
    }
}
