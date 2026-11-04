<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

trait WritesExceptionInLog
{
    public static function writeExceptionInLog(
        Throwable $exception,
        $message = null,
        $statusCode = null,
        $errors = null
    ): void {
        try {
            $logMessage = $message ?? $exception->getMessage();
            Log::warning(
                message: $logMessage,
                context: formatExceptionPayload(
                    exception: $exception,
                    message: $message,
                    statusCode: $statusCode,
                    errors: $errors
                )
            );
        } catch (Throwable $loggingException) {
            try {
                Log::channel('fallback')->error(
                    'Exception logging failed',
                    [
                        'logging_error' => $loggingException->getMessage(),
                        'original_exception' => $exception->getMessage(),
                    ]
                );
            } catch (\Throwable) {
                // Logging must never interrupt the original operation.
            }
        }
    }
}
