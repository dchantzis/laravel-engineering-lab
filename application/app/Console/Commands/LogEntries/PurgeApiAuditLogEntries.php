<?php

namespace App\Console\Commands\LogEntries;

use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Exception;

#[Signature('app:purge-api-audit-log-entries')]
#[Description('Deleting all entries older than x days from the api_audit_logs table')]
class PurgeApiAuditLogEntries extends PurgeLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-api-audit-log-entries
    {--days= : When specified all entries older than this number of days will be deleted. Defaults to .env attribute API_AUDIT_LOG_NUM_DAYS_PURGE }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deleting all entries older than x days from the api_audit_logs table';

    /**
     * PurgeApiAuditLogEntries constructor.
     */
    public function __construct()
    {
        parent::__construct(LogSystem::api(), config('engineering.api_audit_log_num_days_purge'));
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        parent::handle();
    }

    public function fulfill(): array
    {
        return [
            'purge_result' => parent::fulfill(),
        ];
    }
}
