<?php

namespace App\Services\DatabaseLogServices;

use App\Enums\ApiAuditLogType;
use App\Models\ApiAuditLog;
use App\Repositories\ApiAuditLogRepository;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Ramsey\Uuid\UuidInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use App\Services\Security\SensitiveDataSanitizer;

class ApiAuditLogService extends LogService
{
    public function __construct()
    {
        $this->model = (new ApiAuditLog);
    }

    public function add(
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
        if (! config('engineering.api_audit_logs_enabled')) {
            return null;
        }

        $sanitizer = app(SensitiveDataSanitizer::class);

        $content = $sanitizer->sanitizeContent($content);
        $meta = $sanitizer->sanitize($meta);
        $resource = $resource !== null
            ? $sanitizer->sanitizeUrl($resource)
            : null;

        return ApiAuditLogRepository::store(
            content: $content,
            requestUuid: $requestUuid,
            type: $type,
            meta: $meta,
            httpStatusCode: $httpStatusCode,
            ipTraceId: $ipTraceId,
            oauthClientId: $oauthClientId,
            userId: $userId,
            resource: $resource,
        );
    }

    public function addForAsyncResponse(
        $content,
        string|UuidInterface|null $requestUuid,
        ?ApiAuditLog $relatedApiAuditLog = null
    ): ?ApiAuditLog {
        if (! $relatedApiAuditLog) {
            return $this->add(
                content: $content,
                requestUuid: $requestUuid ?: '',
                type: ApiAuditLogType::ASYNC_RESPONSE
            );
        }

        return $this->add(
            content: $content,
            requestUuid: $requestUuid ?: '',
            type: ApiAuditLogType::ASYNC_RESPONSE,
            ipTraceId: $relatedApiAuditLog->ip_trace_id,
            oauthClientId: $relatedApiAuditLog->oauth_client_id,
            userId: $relatedApiAuditLog->user_id,
            resource: $relatedApiAuditLog->resource
        );
    }

    public function find(int $id): ?ApiAuditLog
    {
        return ApiAuditLogRepository::getById($id);
    }

    public function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return ApiAuditLogRepository::paginate(
            $perPage,
            $startDate,
            $endDate
        );
    }

    public function get(
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
        return ApiAuditLogRepository::getEntries(
            $limit,
            $offset,
            $startDate,
            $endDate,
            $ipTraceId,
            $oauthClientId,
            $userId,
            $requestUuid,
        );
    }

    public function countByRequestUuid(
        string|UuidInterface|null $requestUuid
    ): int {
        return ApiAuditLogRepository::countByRequestUuid($requestUuid);
    }

    public function searchByRequestUuid(
        string|UuidInterface|null $requestUuid,
        ?string $oauthClientId
    ): mixed
    {
        return ApiAuditLogRepository::searchByRequestUuid(
            $requestUuid,
            $oauthClientId
        );
    }

    public function checkRequestIsProcessed(
        string|UuidInterface|null $requestUuid,
        string $oauthClientId
    ): bool {
        return ApiAuditLogRepository::checkRequestIsProcessed(
            $requestUuid,
            $oauthClientId
        );
    }

    public function getProcessedRequest(
        string|UuidInterface|null $requestUuid,
        string $oauthClientId
    ): ?ApiAuditLog {
        return ApiAuditLogRepository::getAsyncResponse(
            $requestUuid,
            $oauthClientId
        );
    }

    public function paginateClientEntries(
        ?int $perPage,
        string $oauthClientId,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): LengthAwarePaginator
    {
        return ApiAuditLogRepository::buildPaginateClientEntries(
            $oauthClientId,
            $startDate,
            $endDate
        )->paginate($perPage);
    }
}
