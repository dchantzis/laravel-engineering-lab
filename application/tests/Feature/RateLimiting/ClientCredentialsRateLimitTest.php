<?php

namespace Tests\Feature\RateLimiting;

use App\Models\OauthClientRateLimit;
use Laravel\Passport\Client;
use Tests\PassportTestCase;

class ClientCredentialsRateLimitTest extends PassportTestCase
{
    public function test_client_credentials_requests_are_rate_limited(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        OauthClientRateLimit::query()->create([
            'client_id' => $client->id,
            'requests_per_minute' => 2,
        ]);

        $token = $this->issueClientToken($client);

        $this->withToken($token)
            ->getJson('/api/test')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/test')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/test')
            ->assertStatus(429);
    }

    public function test_different_clients_have_independent_rate_limits(): void
    {
        $clientA = Client::factory()->asClientCredentials()->create();
        $clientB = Client::factory()->asClientCredentials()->create();

        foreach ([$clientA, $clientB] as $client) {
            OauthClientRateLimit::query()->create([
                'client_id' => $client->id,
                'requests_per_minute' => 2,
            ]);
        }

        $tokenA = $this->issueClientToken($clientA);
        $tokenB = $this->issueClientToken($clientB);

        // Client A exhausts its allowance.
        $this->withToken($tokenA)->getJson('/api/test')->assertOk();
        $this->withToken($tokenA)->getJson('/api/test')->assertOk();
        $this->withToken($tokenA)->getJson('/api/test')->assertStatus(429);

        // Client B still has its own allowance.
        $this->withToken($tokenB)->getJson('/api/test')->assertOk();
        $this->withToken($tokenB)->getJson('/api/test')->assertOk();
        $this->withToken($tokenB)->getJson('/api/test')->assertStatus(429);
    }

    public function test_multiple_tokens_from_same_client_share_rate_limit(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        OauthClientRateLimit::query()->create([
            'client_id' => $client->id,
            'requests_per_minute' => 2,
        ]);

        // Issue two different access tokens for the same OAuth client.
        $tokenA = $this->issueClientToken($client);
        $tokenB = $this->issueClientToken($client);

        $this->assertNotSame($tokenA, $tokenB);

        // First request using token A.
        $this->withToken($tokenA)
            ->getJson('/api/test')
            ->assertOk();

        // Second request using token B.
        $this->withToken($tokenB)
            ->getJson('/api/test')
            ->assertOk();

        // Both tokens have exhausted the client's shared quota.
        $this->withToken($tokenA)
            ->getJson('/api/test')
            ->assertStatus(429);

        $this->withToken($tokenB)
            ->getJson('/api/test')
            ->assertStatus(429);
    }

    public function test_client_credentials_rate_limiting_can_be_disabled(): void
    {
        config()->set('rate-limiting.enabled', false);

        $client = Client::factory()->asClientCredentials()->create();

        OauthClientRateLimit::query()->create([
            'client_id' => $client->id,
            'requests_per_minute' => 2,
        ]);

        $token = $this->issueClientToken($client);

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)
                ->getJson('/api/test')
                ->assertOk();
        }
    }
}
