<?php

namespace App\Exceptions;

use App\Services\ExceptionNotifier;
use Throwable;

class ExceptionReporter
{
    public function __construct(
        private readonly ExceptionNotifier $notifier,
    ) {}

    public function report(Throwable $exception, array $classification): void
    {
        $status = $classification['status'];

        if ($status < 500) {
            return;
        }

        try {
            $this->notifier->notify(
                exception: $exception,
                message: $classification['message'],
                statusCode: $status,
                errors: $classification['errors'],
            );
        } catch (Throwable $notificationException) {
            // Notification failures must not interrupt exception handling.
        }
    }
}
