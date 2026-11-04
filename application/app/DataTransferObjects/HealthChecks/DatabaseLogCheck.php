<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class DatabaseLogCheck
{
    public function __construct(
        public ?string $status,
        public ?string $exceptionMessage,
    ) {
    }
}
