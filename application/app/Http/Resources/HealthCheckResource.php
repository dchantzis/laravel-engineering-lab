<?php

namespace App\Http\Resources;

use App\DataTransferObjects\HealthChecks\DatabaseConnectionItem;
use App\DataTransferObjects\HealthChecks\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var HealthCheck $this */
        return [
            'application' => $this->applicationName,
            'url' => $this->url,
            'status' => $this->status,
            'http_code' => $this->httpStatus,
            'now' => $this->nowDateAndTime,
            'now_timestamp' => $this->nowTimestamp,
            'timezone' => $this->timezone,
        ];
    }

    public function adminToArray(Request $request): array
    {
        $databaseConnections = [];
        foreach ($this->databaseConnectionsData->items as $item) {
            /** @var DatabaseConnectionItem $item */
            $databaseConnections[$item->name] = [
                'status' => $item->status,
                'now' => $item->dateTimeNow,
                'mysql_timezone' => [
                    'global' => $item->mysqlTimezoneGlobal,
                    'session' => $item->mysqlTimezoneSession,
                ],
                'env_timezone' => $item->envTimezone,
                'exception' => $item->exceptionMessage ?? 'N/A',
            ];
        }

        /** @var HealthCheck $this */
        return [
            'application' => $this->applicationName,
            'url' => $this->url,
            'status' => $this->status,
            'http_code' => $this->httpStatus,
            'environment' => $this->environment,
            'now' => $this->nowDateAndTime,
            'now_timestamp' => $this->nowTimestamp,
            'timezone' => $this->timezone,
            'database_connections' => $databaseConnections,
            'database_log' => [
                'status' => $this->databaseLogData->status,
                'exception' => $this->databaseLogData->exceptionMessage ?? 'N/A',
            ],
            'logs' => [
                'status' => $this->logFileCheckData->status,
                'log_directory_exists' => $this->logFileCheckData->logDirectoryExists,
                'log_directory_is_writable' => $this->logFileCheckData->logDirectoryIsWritable,
                'log_file_exists' => $this->logFileCheckData->logFileExists,
                'log_file_is_writable' => $this->logFileCheckData->logFileIsWritable,
                'log_filename' => $this->logFileCheckData->logFileName,
                'exception' => $this->logFileCheckData->exceptionMessage ?? 'N/A',
            ],
            'cache' => $this->cacheCheckData ?? 'Not Active',
            'engineering_config' => $this->engineeringConfig,
            'app_config' => isEnvironmentLocal() ? $this->appConfig : 'N/A',
            'queue_config' => isEnvironmentLocal() ? $this->queueConfig : 'N/A',
            'logging_config' => isEnvironmentLocal() ? $this->loggingConfig : 'N/A',
        ];
    }
}
