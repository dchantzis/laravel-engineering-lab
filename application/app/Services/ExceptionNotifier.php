<?php

namespace App\Services;

use App\Enums\MessageLevel;
use Throwable;

class ExceptionNotifier
{
    public function notify(
        Throwable $exception,
        string $message,
        int $statusCode,
        array $errors = [],
    ): void {
        Notify::exceptionChannel(
            exception: $exception,
            message: $message,
            level: MessageLevel::WARNING,
            statusCode: $statusCode,
            errors: $errors,
            writeInLog: false,
        );
    }
}
