<?php

namespace App\Services\DatabaseLogServices;

use App\Models\ApiAuditLog;
use App\Models\ConsoleCommandLog;
use App\Models\IpTrace;
use App\Models\SystemLog;
use App\Traits\MassDeleteEntries;
use Carbon\Carbon;
use DateTimeInterface;

abstract class LogService
{
    use MassDeleteEntries;

    protected ConsoleCommandLog|SystemLog|IpTrace|ApiAuditLog $model;

    abstract public function find(int $id);

    abstract public function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    );

    protected function model(
    ): ConsoleCommandLog|SystemLog|IpTrace|ApiAuditLog {
        return $this->model;
    }
}
