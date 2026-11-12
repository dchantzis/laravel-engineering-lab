<?php

namespace App\Services;

use App\Models\OauthClientRateLimit;

class OauthClientRateLimitResolver
{
    public function resolve(string $clientId): int
    {
        $override = OauthClientRateLimit::query()
            ->where('client_id', $clientId)
            ->value('requests_per_minute');

        return $override !== null
            ? (int) $override
            : (int) config('rate-limiting.api.client_credentials');
    }
}
