<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\HealthCheckResource;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class HealthCheckController extends BaseController
{
    public function __construct(protected HealthCheckService $service)
    {
    }

    public function healthCheck(Request $request): JsonResponse
    {
        $healthCheckData = $this->service->healthChecks($request);

        return response()->api(
            new HealthCheckResource(
                $healthCheckData
            ),
            [],
            $healthCheckData->httpStatus
        );
    }
}
