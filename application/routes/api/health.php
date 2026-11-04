<?php

use App\Http\Controllers\API\HealthCheckController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IpTrace;

Route::middleware([
//    sprintf('throttle:%s', resolveRateLimiterNameForOauthClientType(request())),
])->group(function () {

    Route::get('healthcheck', [
        HealthCheckController::class, 'healthCheck',
    ])->name('healthcheck');

});
