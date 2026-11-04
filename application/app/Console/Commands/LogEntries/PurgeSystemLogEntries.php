<?php

namespace App\Console\Commands\LogEntries;

use App\Services\LogSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:purge-system-log-entries')]
#[Description('Deleting all entries older than x days from the system_logs table')]
class PurgeSystemLogEntries extends PurgeLogEntries
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-system-log-entries
    {--days= : When specified all entries older than this number of days will be deleted. Defaults to .env attribute SYSTEM_LOG_NUM_DAYS_PURGE }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deleting all entries older than x days from the system_logs table';

    /**
     * PurgeSystemLogEntries constructor.
     */
    public function __construct()
    {
        parent::__construct(LogSystem::system(), config('engineering.system_log_num_days_purge'));
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
            'delete_health_check_log_entries_result' => $this->logService->deleteHealthcheckLogEntries(),
        ];
    }
}
