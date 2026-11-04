<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class ApplySensitiveDataProcessor
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()
            ->pushProcessor(new SensitiveDataProcessor());
    }
}
