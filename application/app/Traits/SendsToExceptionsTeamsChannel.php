<?php

namespace App\Traits;

use App\Enums\TeamsMessageLevel;
use App\Services\Teams;
use Throwable;

trait SendsToExceptionsTeamsChannel
{
    public static function sendExceptionTeamsNotification(
        Throwable $exception,
        ?string $message = null,
        ?TeamsMessageLevel $level = TeamsMessageLevel::WARNING,
        ?int $statusCode = null,
        array|string|null $errors = null
    ): void {
        $level = ($level) ?: TeamsMessageLevel::WARNING;

        if ($errors === null) {
            $errors = (method_exists($exception, 'errors')) ? $exception->errors() : [];
        }

        if ($statusCode === null) {
            $statusCode = (method_exists($exception, 'getCode')) ? $exception->getCode() : null;
        }

        Teams::sendToExceptionChannel(
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

    public static function sendMessageTeamsNotification(
        string $message,
        ?TeamsMessageLevel $level = TeamsMessageLevel::WARNING,
        array|string|null $errors = null,
    ): void {
        Teams::sendToExceptionChannel(
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
