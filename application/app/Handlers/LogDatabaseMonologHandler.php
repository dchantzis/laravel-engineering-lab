<?php

namespace App\Handlers;

use App\Events\LogMonologEvent;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class LogDatabaseMonologHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            event(new LogMonologEvent($record));
        } catch (Throwable $exception) {
            try {
                Log::channel('fallback')->error(
                    'Database logging failed',
                    [
                        'exception' => $exception->getMessage(),
                        'original_message' => $record->message,
                    ]
                );
            } catch (Throwable) {
                // Logging failures must not interrupt the application.
            }
        }
    }
}
