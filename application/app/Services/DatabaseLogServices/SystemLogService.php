<?php

namespace App\Services\DatabaseLogServices;

use App\Models\SystemLog;
use App\Repositories\SystemLogRepository;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Monolog\Level;

class SystemLogService extends LogService
{
    public function __construct()
    {
        $this->model = (new SystemLog);
    }

    public function add(
        string $message,
        Level|int $level,
        array $context,
        string $channel,
        ?int $consoleCommandLogId = null,
        ?int $ipTraceId = null
    ): ?SystemLog {
        return SystemLogRepository::store(
            message: $message,
            level: $level,
            context: $context,
            channel: $channel,
            consoleCommandLogId: $consoleCommandLogId,
            ipTraceId: $ipTraceId
        );
    }

    public function find(int $id): ?SystemLog
    {
        return SystemLogRepository::getById($id);
    }

    public function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return SystemLogRepository::paginate(
            $perPage,
            $startDate,
            $endDate
        );
    }

    public function get(
        ?int $limit,
        ?int $offset,
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?int $ipTraceId,
        ?int $consoleCommandLogId,
    ) {
        return SystemLogRepository::getEntries(
            $limit,
            $offset,
            $startDate,
            $endDate,
            $ipTraceId,
            $consoleCommandLogId
        );
    }

    public function deleteHealthcheckLogEntries(): int
    {
        return SystemLogRepository::deleteHealthcheckLogEntries();
    }
}
