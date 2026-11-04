<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class CacheCheck
{
    public function __construct(
        public ?string $type,
        public ?string $status,
        public ?string $exceptionMessage,
    ) {
    }
}
