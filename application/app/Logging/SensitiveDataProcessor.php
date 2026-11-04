<?php

namespace App\Logging;

use Monolog\LogRecord;

class SensitiveDataProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (
            ! isEnvironmentProduction()
            && ! config('engineering.sensitive_data.enabled', true)
        ) {
            return $record;
        }

        $sensitiveFields = config('engineering.sensitive_data.fields', []);

        $context = sanitizeSensitiveData(
            $record->context,
            $sensitiveFields
        );

        return $record->with(context: $context);
    }
}
