<?php

namespace App\Services;

use App\Handlers\LogDatabaseMonologHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class LogDatabaseMonolog
{
    public function __invoke(array $config): Logger
    {
        $formatter = (new LineFormatter('%message%'))
            ->includeStacktraces(true);

        $handler = (new LogDatabaseMonologHandler(
            level: Logger::toMonologLevel($config['level'] ?? 'debug')
        ))->setFormatter($formatter);

        return (new Logger('database'))
            ->pushHandler($handler)
            ->pushProcessor(new PsrLogMessageProcessor());
    }
}
