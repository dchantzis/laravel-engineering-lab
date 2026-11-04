<?php

namespace Tests\Feature\Auth;

use Laravel\Passport\Passport;
use Tests\PassportTestCase;

class PassportPkceTest extends PassportTestCase
{
    public function test_admin_can_login_approve_pkce_exchange_and_access_admin_api(): void
    {
        $admin = $this->createUser(true);
        $client = $this->publicClient();
        $this->get($this->authorizationUrl($client))->assertRedirect('/login');
        $intended = session('url.intended');
        $this->post('/login', ['email' => $admin->email, 'password' => 'test-password'])
            ->assertRedirect($intended);
        $this->assertAuthenticatedAs($admin, 'web');
        $code = $this->approve($client);

        $response = $this->exchange($client, $code)->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);

        $this->assertNotEmpty($response->json('refresh_token'));
        $token = $response->json('access_token');
        $claims = $this->parseToken($token)->claims();
        $this->assertSame((string) $admin->id, $claims->get('sub'));
        $this->assertSame([$client->id], $claims->get('aud'));
        $stored = Passport::token()->findOrFail($claims->get('jti'));
        $this->assertSame($admin->id, $stored->user_id);
        $this->assertSame($client->id, $stored->client_id);
        $this->clearSessionAuthentication();
        $this->withToken($token)->getJson('/api/admin/test')->assertOk()
            ->assertJsonPath('data.message', 'API routing works');
        $this->assertAuthenticatedAs($admin, 'api');
    }

    public function test_incorrect_pkce_verifier_is_rejected_without_issuing_token(): void
    {
        $this->actingAs($this->createUser(true), 'web');
        $client = $this->publicClient();
        $code = $this->approve($client);

        $this->exchange($client, $code, str_repeat('x', 64))->assertBadRequest()
            ->assertJsonPath('error', 'invalid_grant')->assertJsonMissingPath('access_token');

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_missing_pkce_verifier_is_rejected_without_issuing_token(): void
    {
        $this->actingAs($this->createUser(true), 'web');
        $client = $this->publicClient();
        $code = $this->approve($client);

        $this->exchange($client, $code, null)->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request')->assertJsonMissingPath('access_token');

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_authorization_code_cannot_be_exchanged_twice(): void
    {
        $this->actingAs($this->createUser(true), 'web');
        $client = $this->publicClient();
        $code = $this->approve($client);
        $this->exchange($client, $code)->assertOk();
        $count = Passport::token()->count();
        $this->assertSame(1, $count);

        $this->exchange($client, $code)->assertBadRequest()
            ->assertJsonPath('error', 'invalid_grant')->assertJsonMissingPath('access_token');

        $this->assertSame($count, Passport::token()->count());
    }

    public function test_guest_authorization_request_requires_login(): void
    {
        $client = $this->publicClient();

        $this->get($this->authorizationUrl($client))->assertRedirect('/login');

        $this->assertGuest('web');
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_authorization_denial_redirects_with_access_denied_and_original_state(): void
    {
        $this->actingAs($this->createUser(true), 'web');
        $client = $this->publicClient();
        $consent = $this->consent($client);

        $response = $this->delete('/oauth/authorize', [
            'client_id' => $client->id,
            'state' => 'test-state',
            'auth_token' => $consent->viewData('authToken'),
        ])->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('test-state', $query['state']);
        $this->assertArrayNotHasKey('code', $query);
        $this->assertDatabaseCount('oauth_auth_codes', 0);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }
}
