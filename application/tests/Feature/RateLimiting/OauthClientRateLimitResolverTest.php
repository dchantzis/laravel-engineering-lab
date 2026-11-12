<?php

namespace Tests\Feature\RateLimiting;

use App\Services\OauthClientRateLimitResolver;
use Tests\PassportTestCase;
use Illuminate\Support\Str;
use App\Models\OauthClientRateLimit;
use Laravel\Passport\Client;

class OauthClientRateLimitResolverTest extends PassportTestCase
{
    public function test_client_without_override_uses_default_limit(): void
    {
        config()->set('rate-limiting.api.client_credentials', 300);

        $clientId = Str::uuid()->toString();

        $limit = app(OauthClientRateLimitResolver::class)
            ->resolve($clientId);

        $this->assertSame(300, $limit);
    }

    public function test_client_with_override_uses_configured_limit(): void
    {
        config()->set('rate-limiting.api.client_credentials', 300);

        $client = Client::factory()->asClientCredentials()->create();

        OauthClientRateLimit::query()->create([
            'client_id' => $client->id,
            'requests_per_minute' => 150,
        ]);

        $limit = app(OauthClientRateLimitResolver::class)
            ->resolve($client->id);

        $this->assertSame(150, $limit);
    }

    public function test_updating_client_override_changes_resolved_limit(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $override = OauthClientRateLimit::query()->create([
            'client_id' => $client->id,
            'requests_per_minute' => 150,
        ]);

        $resolver = app(OauthClientRateLimitResolver::class);

        $this->assertSame(150, $resolver->resolve($client->id));

        $override->update([
            'requests_per_minute' => 500,
        ]);

        $this->assertSame(500, $resolver->resolve($client->id));
    }


}
