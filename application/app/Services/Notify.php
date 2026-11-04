<?php

namespace App\Services;

use App\Enums\MessageLevel;
use App\Traits\WritesExceptionInLog;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Traits\SendsToExceptionsChannel;

class Notify
{
    use SendsToExceptionsChannel;
    use WritesExceptionInLog;

    public static function scheduledCommandLogChannel(
        string $message,
        array $content,
        ?MessageLevel $level = MessageLevel::INFO,
        bool $writeInLog = true,
    ): void {
        if ($writeInLog) {
            Log::info($message, $content);
        }

        Notifications::sendToScheduledCommandsLogChannel(
            title: $message,
            content: $content,
            level: $level
        );
    }

    public static function exceptionChannel(
        Throwable $exception,
        ?string $message = null,
        ?MessageLevel $level = MessageLevel::WARNING,
        $statusCode = null,
        $errors = null,
        bool $writeInLog = true,
    ): void {
        if ($writeInLog) {
            static::writeExceptionInLog(
                $exception,
                $message,
                $statusCode,
                $errors
            );
        }

        static::sendExceptionNotification(
            $exception,
            $message,
            $level,
            $statusCode,
            $errors
        );
    }
}
