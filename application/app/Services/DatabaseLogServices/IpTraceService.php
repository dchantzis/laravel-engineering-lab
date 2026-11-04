<?php

namespace App\Services\DatabaseLogServices;

use App\Models\IpTrace;
use App\Repositories\IpTraceRepository;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class IpTraceService extends LogService
{
    public function __construct()
    {
        $this->model = (new IpTrace);
    }

    public function add(
        string $ipAddress,
        string $requestMethod,
        string $route,
        string|array $requestParameters,
        string|array $requestBody,
        string|array $requestHeader,
        string $host,
    ): IpTrace {
        return IpTraceRepository::store(
            ipAddress: $ipAddress,
            requestMethod: $requestMethod,
            route: $route,
            requestParameters: $requestParameters,
            requestBody: $requestBody,
            requestHeader: $requestHeader,
            host: $host,
            ignoredFields: config('engineering.sensitive_data.fields', [])
        );
    }

    public function find(int $id): ?IpTrace
    {
        return IpTraceRepository::getById($id);
    }

    public function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return IpTraceRepository::paginate(
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
        ?string $ipAddress = null
    ) {
        return IpTraceRepository::getEntries(
            limit: $limit,
            offset: $offset,
            startDate: $startDate,
            endDate: $endDate,
            ipAddress: $ipAddress
        );
    }
}
