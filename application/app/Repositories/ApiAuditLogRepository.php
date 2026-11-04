<?php

namespace App\Repositories;

use App\Enums\ApiAuditLogType;
use App\Models\ApiAuditLog;
use App\Traits\FilterableByDateDuration;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Ramsey\Uuid\UuidInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

class ApiAuditLogRepository
{
    use FilterableByDateDuration;

    public static function store(
        $content,
        string $requestUuid,
        ApiAuditLogType $type,
        $meta = [],
        ?int $httpStatusCode = null,
        ?int $ipTraceId = null,
        ?string $oauthClientId = null,
        ?int $userId = null,
        ?string $resource = null,
    ): ?ApiAuditLog {
        try {
            return ApiAuditLog::create(
                [
                    'request_uuid' => $requestUuid,
                    'ip_trace_id' => $ipTraceId,
                    'oauth_client_id' => $oauthClientId,
                    'user_id' => $userId,
                    'resource' => $resource,
                    'type' => $type->value,
                    'content' => $content,
                    'http_status_code' => $httpStatusCode,
                    'meta' => $meta,
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public static function getById(
        int $id
    ): ?ApiAuditLog {
        return ApiAuditLog::find($id);
    }

    public static function getEntries(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate,
        ?int $ipTraceId,
        ?string $oauthClientId,
        ?int $userId,
        ?string $requestUuid,
    ): EloquentCollection|Collection
    {
        return self::buildFilterQuery(
            $limit,
            $offset,
            $startDate,
            $endDate,
            $ipTraceId,
            $oauthClientId,
            $userId,
            $requestUuid,
        )->get();
    }

    public static function buildFilterQuery(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate,
        ?int $ipTraceId,
        ?string $oauthClientId,
        ?int $userId,
        ?string $requestUuid,
    ): EloquentBuilder|QueryBuilder
    {
        $query = ApiAuditLog::orderBy('created_at', 'DESC');

        if (! is_null($limit) && ! is_null($offset)) {
            $query->limit($limit)
                ->offset($offset);
        }

        if ($ipTraceId) {
            $query->where('ip_trace_id', $ipTraceId);
        }

        if ($oauthClientId) {
            $query->where('oauth_client_id', $oauthClientId);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($requestUuid) {
            $query->where('request_uuid', $requestUuid);
        }

        return static::inDateDuration(
            $query,
            $startDate,
            $endDate
        );
    }

    public static function buildPaginateClientEntries(
        string $oauthClientId,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): EloquentBuilder|QueryBuilder
    {
        $query = ApiAuditLog::where('oauth_client_id', $oauthClientId)
            ->orderBy('created_at', 'DESC');

        return static::inDateDuration(
            $query,
            $startDate,
            $endDate
        );
    }

    public static function countByRequestUuid(
        string|UuidInterface|null $requestUuid
    ): int {
        return ApiAuditLog::where('request_uuid', $requestUuid)
            ->count();
    }

    public static function searchByRequestUuid(
        string|UuidInterface|null $requestUuid,
        ?string $oauthClientId
    ) {
        return ApiAuditLog::where(function ($query) use ($requestUuid, $oauthClientId) {
            if ($oauthClientId) {
                $query->where('oauth_client_id', $oauthClientId);
            }

            $query->where('request_uuid', $requestUuid);
        })
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function getAsyncResponse(
        string|UuidInterface|null $requestUuid,
        ?string $oauthClientId
    ): ?ApiAuditLog {
        return ApiAuditLog::where('request_uuid', $requestUuid)
            ->where(function ($query) use ($oauthClientId) {
                if ($oauthClientId) {
                    $query->where('oauth_client_id', $oauthClientId);
                }
            })
            ->where(function ($query) {
                $query->where('type', ApiAuditLogType::ASYNC_RESPONSE->value);
            })
            ->first();
    }

    public static function checkRequestIsProcessed(
        string|UuidInterface|null $requestUuid,
        string $oauthClientId
    ): bool {
        return (bool) ApiAuditLog::where('oauth_client_id', $oauthClientId)
            ->where('request_uuid', $requestUuid)
            ->where(function ($query) {
                $query->where('type', ApiAuditLogType::ASYNC_RESPONSE->value);
            })
            ->count();
    }

    public static function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return ApiAuditLog::byDuration(
                $startDate,
                $endDate
            )
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage);
    }

    public static function checkAsyncResponseAlreadyExists(
        string|UuidInterface|null $requestUuid
    ): bool {
        return (bool) ApiAuditLog::where('request_uuid', $requestUuid)
            ->where('type', ApiAuditLogType::ASYNC_RESPONSE->value)
            ->count();
    }
}
