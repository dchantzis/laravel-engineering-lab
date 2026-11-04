<?php

namespace App\Listeners;

use App\Services\DatabaseLogServices\SystemLogService;
use App\Services\LogSystem;

class LogMonologEventListener
{
    /**
     * @var SystemLogService
     */
    protected SystemLogService $systemLogService;

    public function __construct()
    {
        $this->systemLogService = LogSystem::system();
    }

    public function handle($events): void
    {
        $this->systemLogService->add(
            message: $events->record['formatted'],
            level: $events->record['level'],
            context: $events->record['context'],
            channel: $events->record['channel'],
            consoleCommandLogId: null,
            ipTraceId: (request()) ? request()->attributes->get('ip_trace_id') : null,
        );
    }
}
