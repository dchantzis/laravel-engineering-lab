<?php

namespace App\Traits;

use App\Enums\SlackMessageLevel;
use App\Services\Slack;
use Throwable;

trait SendsToExceptionsSlackChannel
{
    public static function sendExceptionSlackNotification(
        Throwable $exception,
        ?string $message = null,
        ?SlackMessageLevel $level = SlackMessageLevel::WARNING,
        $statusCode = null,
        $errors = null
    ): void {
        $level = ($level) ?: SlackMessageLevel::WARNING;

        if (! $errors) {
            $errors = (method_exists($exception, 'errors')) ? json_encode($exception->errors()) : [];
        }

        if (! $statusCode) {
            $statusCode = (method_exists($exception, 'getCode')) ? $exception->getCode() : null;
        }

        Slack::sendToExceptionChannel(
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

    public static function sendMessageSlackNotification(
        string $message,
        ?SlackMessageLevel $level = SlackMessageLevel::WARNING,
        $errors = null,
    ): void {
        Slack::sendToExceptionChannel(
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
