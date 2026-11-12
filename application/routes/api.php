<?php

use App\Http\Middleware\ApiAuditLog;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\GenerateApiRequestUuid;
use App\Http\Middleware\IpTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureOauthClientIsResourceOwner;
use App\Enums\RateLimiterName;

require __DIR__.'/api/health.php';

Route::middleware([
    IpTrace::class,
    EnsureOauthClientIsResourceOwner::class,
    'throttle:'.RateLimiterName::API_CLIENT_CREDENTIALS->value,
    GenerateApiRequestUuid::class,
    ApiAuditLog::class,
])->group(function () {
    Route::get('/test', function (Request $request) {
        return response()->api([
            'message' => 'API routing works',
            'oauth_client_id' => $request->attributes->get(
                EnsureOauthClientIsResourceOwner::OAUTH_CLIENT_ID_SLUG
            ),
            'api_request_uuid' => (string) $request->attributes->get(
                GenerateApiRequestUuid::API_REQUEST_UUID_SLUG
            ),
        ]);
    });
});

Route::prefix('admin')
    ->middleware([
        'auth:api',
        EnsureUserIsAdmin::class,
        'throttle:'.RateLimiterName::API_ADMIN->value,
    ])
    ->group(function () {

        Route::get('/test', function (Request $request) {
            return response()->api([
                'message' => 'API routing works',
            ]);
        });

    });
