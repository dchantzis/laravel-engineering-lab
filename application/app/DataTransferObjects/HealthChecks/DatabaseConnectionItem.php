<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class DatabaseConnectionItem
{
    public function __construct(
        public ?string $name,
        public ?string $status,
        public ?string $dateTimeNow,
        public ?string $mysqlTimezoneGlobal,
        public ?string $mysqlTimezoneSession,
        public ?string $envTimezone,
        public ?string $exceptionMessage,
    ) {
    }
}
