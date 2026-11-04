<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Monolog\LogRecord;

class LogMonologEvent
{
    use SerializesModels;

    public LogRecord $record;

    public function __construct(LogRecord $record)
    {
        $this->record = $record;
    }
}
