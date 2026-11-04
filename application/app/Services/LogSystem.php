<?php

namespace App\Services;

use App\Services\DatabaseLogServices\ApiAuditLogService;
use App\Services\DatabaseLogServices\ConsoleCommandLogService;
use App\Services\DatabaseLogServices\IpTraceService;
use App\Services\DatabaseLogServices\SystemLogService;
use Illuminate\Support\Facades\App;

class LogSystem
{
    /** @var ConsoleCommandLogService */
    private static $console;

    /** @var IpTraceService */
    private static $ipTrace;

    /** @var SystemLogService */
    private static $system;

    /** @var ApiAuditLogService */
    private static $api;

    public static function console(): ConsoleCommandLogService
    {
        if (! self::$console) {
            self::$console = App::make(ConsoleCommandLogService::class);
        }

        return self::$console;
    }

    public static function ipTrace(): IpTraceService
    {
        if (! self::$ipTrace) {
            self::$ipTrace = App::make(IpTraceService::class);
        }

        return self::$ipTrace;
    }

    public static function system(): SystemLogService
    {
        if (! self::$system) {
            self::$system = App::make(SystemLogService::class);
        }

        return self::$system;
    }

    public static function api(): ApiAuditLogService
    {
        if (! self::$api) {
            self::$api = App::make(ApiAuditLogService::class);
        }

        return self::$api;
    }
}
