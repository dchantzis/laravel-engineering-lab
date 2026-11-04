<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class DatabaseConnections
{
    public function __construct(
        public ?array $items
    ) {
    }
}
