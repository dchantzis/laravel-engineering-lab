<?php

namespace App\Providers;

use App\Http\Responses\ApiResponse;
use Error;
use Exception;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

class ApiResponseMacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro(
            'api',
            function (
                $data = [],
                $meta = [],
                $status = HttpFoundationResponse::HTTP_OK,
                Exception|Error|null $exception = null,
                array $headers = [],
                $options = 0
            ) {
                return new ApiResponse(
                    $data,
                    $meta,
                    $status,
                    $exception,
                    $headers,
                    $options
                )->toResponse();
            });
    }
}
