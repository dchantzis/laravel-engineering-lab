<?php

namespace App\Repositories;

use App\Models\IpTrace;
use App\Traits\FilterableByDateDuration;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

class IpTraceRepository
{
    use FilterableByDateDuration;

    public static function getById(
        int $id
    ): ?IpTrace {
        return IpTrace::find($id);
    }

    public static function getEntries(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate,
        ?string $ipAddress = null
    ) {
        return self::buildFilterQuery(
            limit: $limit,
            offset: $offset,
            startDate: $startDate,
            endDate: $endDate,
            ipAddress: $ipAddress
        )->get();
    }

    protected static function buildFilterQuery(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate,
        ?string $ipAddress = null
    ): EloquentBuilder {
        $query = IpTrace::orderBy('created_at', 'DESC');

        if (! is_null($ipAddress)) {
            $query->where('ip_address', $ipAddress);
        }

        if (! is_null($limit) && $limit >= 0) {
            $query->limit($limit);

            if (! is_null($offset)) {
                $query->offset($offset);
            }
        }

        return static::inDateDuration(
            query: $query,
            startDate: $startDate,
            endDate: $endDate
        );
    }

    public static function store(
        string $ipAddress,
        string $requestMethod,
        string $route,
        string|array $requestParameters,
        string|array $requestBody,
        string|array $requestHeader,
        string $host,
        array $ignoredFields
    ): ?IpTrace {
        $requestParameters = sanitizeSensitiveData($requestParameters, $ignoredFields);
        $requestBody = sanitizeSensitiveData($requestBody, $ignoredFields);
        $requestHeader = sanitizeSensitiveData(
            $requestHeader,
            [...$ignoredFields, 'authorization', 'cookie', 'proxy-authorization', 'x-api-key']
        );

        return IpTrace::create([
            'ip_address' => $ipAddress,
            'request_method' => $requestMethod,
            'route' => $route,
            'request_parameters' => jsonEncode($requestParameters),
            'request_body' => jsonEncode($requestBody),
            'request_header' => jsonEncode($requestHeader),
            'host' => $host,
        ]);
    }



    public static function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return IpTrace::byDuration(
            $startDate,
            $endDate
        )
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage);
    }
}
