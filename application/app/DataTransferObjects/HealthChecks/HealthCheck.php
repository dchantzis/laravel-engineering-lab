<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class HealthCheck
{
    public function __construct(
        public ?string $applicationName,
        public ?string $url,
        public ?string $status,
        public ?int $code,
        public ?int $httpStatus,
        public ?string $environment,
        public ?string $nowDateAndTime,
        public ?int $nowTimestamp,
        public ?string $timezone,
        public ?DatabaseConnections $databaseConnections,
        public ?CacheCheck $cacheCheck,
        public ?DatabaseLogCheck $databaseLog,
        public ?LogFileCheck $logFileCheck,
        public ?EnvironmentParametersCheck $engineeringConfig,
        public ?EnvironmentParametersCheck $appConfig,
        public ?EnvironmentParametersCheck $queueConfig,
        public ?EnvironmentParametersCheck $loggingConfig
    ) {
    }
}
