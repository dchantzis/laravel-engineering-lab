<?php

namespace App\Console\Commands\LogEntries;

use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:purge-ip-trace-entries')]
#[Description('Deleting all entries older than x days from the ip_trace table')]
class PurgeIpTraceEntries extends PurgeLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-ip-trace-entries
    {--days= : When specified all entries older than this number of days will be deleted. Defaults to .env attribute IP_TRACE_LOG_NUM_DAYS_PURGE }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deleting all entries older than x days from the ip_trace table';

    /**
     * PurgeApiAuditLogEntries constructor.
     */
    public function __construct()
    {
        parent::__construct(LogSystem::ipTrace(), config('engineering.ip_trace_log_num_days_purge'));
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
