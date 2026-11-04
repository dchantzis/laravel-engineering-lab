<?php

namespace App\Services;

use App\DataTransferObjects\HealthChecks\CacheCheck;
use App\DataTransferObjects\HealthChecks\DatabaseConnectionItem;
use App\DataTransferObjects\HealthChecks\DatabaseConnections;
use App\DataTransferObjects\HealthChecks\DatabaseLogCheck;
use App\DataTransferObjects\HealthChecks\EnvironmentParametersCheck;
use App\DataTransferObjects\HealthChecks\HealthCheck;
use App\DataTransferObjects\HealthChecks\LogFileCheck;
use App\Enums\DatabaseConnection;
use App\Enums\ResponseStatus;
use Carbon\Carbon;
use Carbon\Constants\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Monolog\Level;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Throwable;
use RuntimeException;

class HealthCheckService
{
    const string HEALTH_CHECK_DATABASE_LOG_MESSAGE = 'Health Check Database Log';

    const string HEALTH_CHECK_FILE_LOG_MESSAGE = 'Health Check File Log';

    private function databaseChecks(string $databaseConnection): DatabaseConnectionItem
    {
        $exceptionMessage = null;
        try {
            $envTimezone = config(sprintf(
                'database.connections.%s.timezone',
                $databaseConnection
            ));

            $connection = DB::connection($databaseConnection);

            $connection->getPdo();

            $dateTimeNow = null;
            $timezoneGlobal = null;
            $timezoneSession = null;

            if ($connection->getDriverName() === 'mysql') {
                $dateTimeNowQuery = $connection->select('SELECT NOW() as now');

                $timezoneQuery = $connection->select(
                    'SELECT @@global.time_zone as global_time_zone, @@session.time_zone as session_time_zone'
                );

                $dateTimeNow = Arr::first($dateTimeNowQuery)->now;
                $timezoneGlobal = Arr::first($timezoneQuery)->global_time_zone;
                $timezoneSession = Arr::first($timezoneQuery)->session_time_zone;
            }
        } catch (Throwable $exception) {
            $exceptionMessage = sprintf(
                '%s - %s',
                $exception->getCode(),
                $exception->getMessage()
            );
        }

        return new DatabaseConnectionItem(
            $databaseConnection,
            ($exceptionMessage) ? ResponseStatus::STATUS_ERROR->value : ResponseStatus::STATUS_OK->value,
            $dateTimeNow ?? null,
            $timezoneGlobal ?? null,
            $timezoneSession ?? null,
            $envTimezone ?? null,
            $exceptionMessage ?? null
        );
    }

    private function cacheCheckRedis(): CacheCheck
    {
        $exceptionMessage = null;
        try {
            Redis::ping();
        } catch (Throwable $exception) {
            $exceptionMessage = sprintf(
                '%s - %s',
                $exception->getCode(),
                $exception->getMessage()
            );
        }

        return new CacheCheck(
            'redis',
            ($exceptionMessage) ? ResponseStatus::STATUS_ERROR->value : ResponseStatus::STATUS_OK->value,
            $exceptionMessage ?? null
        );
    }

    private function databaseLogCheck(Request $request): DatabaseLogCheck
    {
        $exceptionMessage = null;
        try {
            $systemLog = LogSystem::system()->add(
                self::HEALTH_CHECK_DATABASE_LOG_MESSAGE,
                Level::Info,
                [],
                app()->environment(),
                null,
                $request->attributes->get('ip_trace_id')
            );

            if ($systemLog === null || ! $systemLog->exists) {
                throw new RuntimeException('Health check system log was not persisted.');
            }
        } catch (Throwable $exception) {
            $exceptionMessage = sprintf(
                '%s - %s',
                $exception->getCode(),
                $exception->getMessage()
            );
        }

        return new DatabaseLogCheck(
            ($exceptionMessage) ? ResponseStatus::STATUS_ERROR->value : ResponseStatus::STATUS_OK->value,
            $exceptionMessage
        );
    }

    private function logFilesCheck(): LogFileCheck
    {
        $status = ResponseStatus::STATUS_ERROR->value;

        try {
            Log::channel('daily')->info(self::HEALTH_CHECK_FILE_LOG_MESSAGE);

            $logPath = config('logging.channels.daily.path');
            $logDirectory = dirname($logPath);

            if (is_dir($logDirectory) && is_writable($logDirectory)) {
                $logPath = config('logging.channels.daily.path');

                $logFileName = preg_replace(
                    '/\.log$/',
                    '-' . date('Y-m-d') . '.log',
                    $logPath
                );

                $logFileExists = file_exists($logFileName);
                $logFileIsWritable = is_writable($logFileName);

                if (file_exists(storage_path($logFileName)) && is_writable(storage_path($logFileName))) {
                    $status = ResponseStatus::STATUS_OK->value;
                }
            }
        } catch (Throwable $exception) {
            $exceptionMessage = sprintf(
                '%s - %s',
                $exception->getCode(),
                $exception->getMessage()
            );
        }

        return new LogFileCheck(
            isset($logDirectory) && is_dir($logDirectory),
            isset($logDirectory) && is_writable($logDirectory),
            $logFileExists ?? false,
            $logFileIsWritable ?? false,
            $logFileName ?? '',
            $status,
            $exceptionMessage ?? null,
        );
    }

    public function healthChecks(
        Request $request,
        bool $includeWriteChecks = false
    ): HealthCheck
    {
        $connections = [
            DatabaseConnection::APPLICATION,
        ];

        $items = [];

        foreach ($connections as $connection) {
            $items[$connection] = $this->databaseChecks($connection);
        }

        $databaseChecks = new DatabaseConnections($items);

        $usesRedis = in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
        ], true);

        $cacheCheck = $usesRedis
            ? $this->cacheCheckRedis()
            : null;

        $databaseLogCheck = $includeWriteChecks
            ? $this->databaseLogCheck($request)
            : null;

        $logFileCheck = $includeWriteChecks
            ? $this->logFilesCheck()
            : null;

        $databaseError = false;

        foreach ($databaseChecks->items as $databaseCheck) {
            if ($databaseCheck->status === ResponseStatus::STATUS_ERROR->value) {
                $databaseError = true;
                break;
            }
        }

        $error = $databaseError
            || ($cacheCheck?->status === ResponseStatus::STATUS_ERROR->value)
            || ($databaseLogCheck?->status === ResponseStatus::STATUS_ERROR->value)
            || ($logFileCheck?->status === ResponseStatus::STATUS_ERROR->value);

        $now = Carbon::now();

        return new HealthCheck(
            applicationName: config('app.name'),
            url: config('app.url'),
            status: ($error) ? ResponseStatus::STATUS_ERROR->value : ResponseStatus::STATUS_OK->value,
            code: ($error) ? HttpFoundationResponse::HTTP_SERVICE_UNAVAILABLE : HttpFoundationResponse::HTTP_OK,
            httpStatus: ($error) ? HttpFoundationResponse::HTTP_SERVICE_UNAVAILABLE : HttpFoundationResponse::HTTP_OK,
            environment: config('app.env'),
            nowDateAndTime: $now->format(Format::DEFAULT_TO_STRING_FORMAT),
            nowTimestamp: $now->timestamp,
            timezone: config('app.timezone'),
            databaseConnections: $databaseChecks,
            cacheCheck: $cacheCheck,
            databaseLog: $databaseLogCheck,
            logFileCheck: $logFileCheck,
            engineeringConfig: (new EnvironmentParametersCheck(config('engineering'))),
            appConfig: isEnvironmentLocal() ? (new EnvironmentParametersCheck(config('app'))) : null,
            queueConfig: isEnvironmentLocal() ? (new EnvironmentParametersCheck(config('queue'))) : null,
            loggingConfig: isEnvironmentLocal() ? (new EnvironmentParametersCheck(config('logging'))) : null
        );
    }
}
