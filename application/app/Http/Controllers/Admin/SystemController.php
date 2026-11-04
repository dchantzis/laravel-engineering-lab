<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ScanSchedulerLocksRequest;
use App\Services\Admin\SchedulerLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class SystemController extends BaseController
{
    public function scanSchedulerLocks(ScanSchedulerLocksRequest $request, SchedulerLockService $schedulerLockService): JsonResponse
    {
        $threshold = $request->validated('threshold');

        $result = $schedulerLockService->scanSchedulerLocks(
            release: (bool) $request->validated('release'),
            showAll: (bool) $request->validated('show_all'),
            thresholdMinutes: $threshold
                ? (int) $threshold
                : (int) config('app.scheduler_commands_scan_orphan_threshold_minutes', 5),
        );

        return response()->api($result);
    }
}
