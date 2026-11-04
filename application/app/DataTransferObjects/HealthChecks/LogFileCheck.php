<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class LogFileCheck
{
    public function __construct(
        public ?bool   $logDirectoryExists,
        public ?bool   $logDirectoryIsWritable,
        public ?bool   $logFileExists,
        public ?bool   $logFileIsWritable,
        public ?string $logFileName,
        public ?string $status,
        public ?string $exceptionMessage,
    ) {
    }
}
