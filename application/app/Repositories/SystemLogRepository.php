<?php

namespace App\Repositories;

use App\Http\Middleware\IpTrace;
use App\Models\SystemLog;
use App\Traits\FilterableByDateDuration;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Monolog\Level;
use Monolog\Logger;

class SystemLogRepository
{
    use FilterableByDateDuration;

    public static function getById(
        int $id
    ): ?SystemLog {
        return SystemLog::find($id);
    }

    public static function getEntries(
        ?int $limit,
        ?int $offset,
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?int $ipTraceId,
        ?int $consoleCommandLogId,
    ) {
        return self::buildFilterQuery(
            $limit,
            $offset,
            $startDate,
            $endDate,
            $ipTraceId,
            $consoleCommandLogId
        )->get();
    }

    public static function buildFilterQuery(
        ?int $limit,
        ?int $offset,
        ?Carbon $startDate,
        ?Carbon $endDate,
        ?int $ipTraceId,
        ?int $consoleCommandLogId,
    ) {
        $query = SystemLog::orderBy('system_logs.created_at', 'desc')
            ->orderBy('system_logs.id', 'desc');

        if (! is_null($limit) && $limit >= 0) {
            $query->limit($limit);

            if (! is_null($offset)) {
                $query->offset($offset);
            }
        }

        if ($ipTraceId) {
            $query->where('ip_trace_id', $ipTraceId);
        }

        if ($consoleCommandLogId) {
            $query->where('console_command_log_id', $consoleCommandLogId);
        }

        return static::inDateDuration(
            $query,
            $startDate,
            $endDate
        );
    }

    public static function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return SystemLog::byDuration(
            $startDate,
            $endDate
        )
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate($perPage);
    }

    public static function store(
        string $message,
        Level|int $level,
        array $context,
        string $channel,
        ?int $consoleCommandLogId = null,
        ?int $ipTraceId = null
    ): ?SystemLog {
        $ipTraceId = $ipTraceId
            ?? request()->attributes->get(IpTrace::IP_TRACE_ID_SLUG);

        $context = (! empty($context)) ? $context : [];

        if (config('engineering.system_logs.sanitize_sensitive_data', true)) {
            $context = sanitizeSensitiveData($context, config('engineering.sensitive_data.fields', []));
        }

        return SystemLog::create([
            'console_command_log_id' => $consoleCommandLogId ?: null,
            'ip_trace_id' => $ipTraceId,
            'message' => $message,
            'level' => $level,
            'level_name' => strtolower(Logger::toMonologLevel($level)->getName()),
            'context' => jsonEncode($context),
            'channel' => $channel,
        ]);
    }
}
