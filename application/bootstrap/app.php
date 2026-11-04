<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Exceptions\ExceptionClassifier;
use App\Exceptions\ExceptionReporter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*')
                ? null
                : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (Throwable $exception) {
            $classification = app(ExceptionClassifier::class)->classify($exception);

            app(ExceptionReporter::class)->report($exception, $classification);

            // Allow Laravel's normal exception logging to continue.
            return null;
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $classification = app(ExceptionClassifier::class)->classify($exception);

            /** @see \App\Providers\ApiResponseMacroServiceProvider::boot() */
            return response()->api(
                $classification['message'],
                $classification['errors'],
                $classification['status'],
                $exception,
                $classification['headers'],
            );
        });
    })->create();
