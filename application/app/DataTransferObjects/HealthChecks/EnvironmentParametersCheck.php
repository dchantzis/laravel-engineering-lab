<?php

namespace App\DataTransferObjects\HealthChecks;

readonly class EnvironmentParametersCheck
{
    public function __construct(
        public ?array $parameters
    ) {
    }
}
