<?php

namespace Tests\Feature\Auth;

use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\PassportTestCase;

class AdminApiAuthorizationTest extends PassportTestCase
{
    public function test_missing_token_returns_401_without_admin_data(): void
    {
        $this->getJson('/api/admin/test')->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_valid_admin_user_token_accesses_admin_api(): void
    {
        $admin = $this->createUser(true);
        $token = $this->userToken($admin);

        $this->withToken($token)->getJson('/api/admin/test')->assertOk()
            ->assertJsonPath('data.message', 'API routing works');

        $this->assertAuthenticatedAs($admin, 'api');
        $this->assertTrue($this->app['auth']->guard('api')->user()->is_admin);
    }

    public function test_valid_non_admin_user_token_returns_403(): void
    {
        $user = $this->createUser();
        $token = $this->userToken($user);

        $this->withToken($token)->getJson('/api/admin/test')->assertForbidden()->assertJsonMissingPath('data');

        $this->assertAuthenticatedAs($user, 'api');
    }

    public function test_malformed_token_returns_401(): void
    {
        $this->withToken('not-a-jwt')->getJson('/api/admin/test')->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_tampered_signature_returns_401_with_an_existing_token_id(): void
    {
        $token = $this->userToken($this->createUser(true));
        $parts = explode('.', $token);
        $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';

        $this->withToken(implode('.', $parts))->getJson('/api/admin/test')
            ->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_expired_signed_user_token_returns_401(): void
    {
        $expiration = Passport::tokensExpireIn();
        try {
            Passport::tokensExpireIn(now()->subHour());
            $token = $this->userToken($this->createUser(true));
        } finally {
            Passport::tokensExpireIn($expiration);
        }
        $this->assertLessThan(time(), $this->parseToken($token)->claims()->get('exp')->getTimestamp());

        $this->withToken($token)->getJson('/api/admin/test')->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_revoked_user_token_returns_401(): void
    {
        $token = $this->userToken($this->createUser(true));
        $id = $this->parseToken($token)->claims()->get('jti');
        Passport::token()->findOrFail($id)->revoke();

        $this->withToken($token)->getJson('/api/admin/test')->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_client_credentials_token_cannot_impersonate_admin(): void
    {
        $this->createUser(true);
        $client = Client::factory()->asClientCredentials()->create();
        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ])->assertOk();

        $this->withToken($response->json('access_token'))->getJson('/api/admin/test')
            ->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_admin_user_token_cannot_access_client_credentials_integration(): void
    {
        $token = $this->userToken($this->createUser(true));

        $this->withToken($token)->getJson('/api/test')->assertUnauthorized()->assertJsonMissingPath('data');
    }

    public function test_non_admin_cannot_override_admin_status_in_parameters_or_headers(): void
    {
        $user = $this->createUser();
        $token = $this->userToken($user);

        $this->withToken($token)->withHeaders(['X-Is-Admin' => 'true'])
            ->getJson('/api/admin/test?is_admin=1')->assertForbidden()->assertJsonMissingPath('data');

        $this->assertAuthenticatedAs($user, 'api');
        $this->assertFalse($user->fresh()->is_admin);
    }
}
