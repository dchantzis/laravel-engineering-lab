<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class ExceptionClassifier
{
    public function classify(Throwable $exception): array
    {
        $status = 500;
        $message = 'System Error';
        $errors = [];
        $headers = [];

        if ($exception instanceof ValidationException) {
            $status = $exception->status;
            $message = $exception->getMessage();
            $errors = $exception->errors();
        } elseif ($exception instanceof AuthenticationException) {
            $status = 401;
            $message = 'Unauthenticated.';
        } elseif ($exception instanceof AuthorizationException) {
            $status = $exception->status() ?? 403;
            $message = $exception->getMessage();
        } elseif ($exception instanceof ModelNotFoundException) {
            $status = 404;
            $message = 'Resource not found.';
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = $exception->getMessage()
                ?: Response::$statusTexts[$status]
                ?? 'HTTP Error';

            $headers = $exception->getHeaders();
        }

        return [
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
            'headers' => $headers,
        ];
    }
}
