<?php

namespace App\Services\DatabaseLogServices;

use App\Models\ConsoleCommandLog;
use App\Repositories\ConsoleCommandLogRepository;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ConsoleCommandLogService extends LogService
{
    public function __construct()
    {
        $this->model = (new ConsoleCommandLog);
    }

    /**
     * @return mixed
     */
    public function start(
        string $name,
        int $memoryUsageStart = null,
        $parameters = null
    ): ConsoleCommandLog
    {
        if (is_array($parameters)) {
            $parameters = Arr::sortRecursive($parameters);
        }

        $parametersString = (is_array($parameters) || is_object($parameters))
            ? json_encode($parameters, JSON_UNESCAPED_UNICODE)
            : $parameters;

        return $this->model->create([
            'name' => $name,
            'memory_usage_start' => formatBytesToHuman($memoryUsageStart ?? memory_get_usage(true)),
            'parameters' => $parametersString,
            'executed_on_host' => getServerHostname(),
        ]);
    }

    public function end(
        int $id,
        int $memoryUsageEnd = null,
        bool $success = false,
        $meta = null
    ): bool
    {
        $metaString = (is_array($meta) || is_object($meta))
            ? json_encode($meta, JSON_UNESCAPED_UNICODE)
            : $meta;

        return $this->model->find($id)->update([
            'memory_usage_end' => formatBytesToHuman($memoryUsageEnd ?? memory_get_peak_usage(true)),
            'success' => $success,
            'meta' => $metaString,
        ]);
    }

    public function find(int $id): ?ConsoleCommandLog
    {
        return ConsoleCommandLogRepository::getById($id);
    }

    public function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return ConsoleCommandLogRepository::paginate(
            $perPage,
            $startDate,
            $endDate
        );
    }

    public function paginateActive(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return ConsoleCommandLogRepository::paginateActive(
            $perPage,
            $startDate,
            $endDate
        );
    }

    public function get(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ) {
        return ConsoleCommandLogRepository::getEntries(
            $limit,
            $offset,
            $startDate,
            $endDate
        );
    }
}
