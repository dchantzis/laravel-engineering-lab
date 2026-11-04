<?php

namespace App\Traits;

use App\Enums\MessageLevel;
use App\Services\Notifications;
use Throwable;

trait SendsToExceptionsChannel
{
    public static function sendExceptionNotification(
        Throwable $exception,
        ?string $message = null,
        ?MessageLevel $level = MessageLevel::WARNING,
        ?int $statusCode = null,
        array|string|null $errors = null
    ): void {
        $level = ($level) ?: MessageLevel::WARNING;

        if ($errors === null) {
            $errors = (method_exists($exception, 'errors')) ? $exception->errors() : [];
        }

        if ($statusCode === null) {
            $statusCode = (method_exists($exception, 'getCode')) ? $exception->getCode() : null;
        }

        Notifications::sendToExceptionChannel(
            [
                'Message' => $message ?? $exception->getMessage(),
                'Status Code' => $statusCode,
                'Exception' => sprintf('[Code:%s] %s', $exception->getCode(), $exception->getMessage()),
                'File:Line' => sprintf('%s:%s.', $exception->getFile(), $exception->getLine()),
                'Exception Type' => get_class($exception),
                'Errors' => ($errors)
                    ? json_encode($errors)
                    : 'N/A',
            ],
            $level
        );
    }

    public static function sendMessageNotification(
        string $message,
        ?MessageLevel $level = MessageLevel::WARNING,
        array|string|null $errors = null,
    ): void {
        Notifications::sendToExceptionChannel(
            [
                'Message' => $message,
                'Errors' => ($errors)
                    ? json_encode($errors)
                    : 'N/A',
            ],
            $level
        );
    }
}
