<?php

namespace Tests\Feature\Auth;

use App\Services\OauthClientResolver;
use Laravel\Passport\Exceptions\AuthenticationException;
use Illuminate\Http\Request;
use Tests\PassportTestCase;
use Laravel\Passport\Passport;
use App\Http\Middleware\EnsureOauthClientIsResourceOwner;

class OauthClientResolverTest extends PassportTestCase
{
    public function test_resolves_oauth_client_id_from_valid_bearer_token(): void
    {
        $admin = $this->createUser(true);

        $this->actingAs($admin, 'web');

        $client = $this->publicClient();
        $code = $this->approve($client);

        $response = $this->exchange($client, $code)->assertOk();

        $token = $response->json('access_token');

        $request = Request::create(
            '/api/test',
            'GET',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        $oauthClientId = app(OauthClientResolver::class)->resolve($request);

        $this->assertSame($client->id, $oauthClientId);
    }

    public function test_rejects_request_without_bearer_token(): void
    {
        $request = Request::create('/api/test', 'GET');

        $this->expectException(AuthenticationException::class);

        app(OauthClientResolver::class)->resolve($request);
    }

    public function test_rejects_malformed_bearer_token(): void
    {
        $request = Request::create(
            '/api/test',
            'GET',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer invalid.jwt.token',
            ],
        );

        $this->expectException(AuthenticationException::class);

        app(OauthClientResolver::class)->resolve($request);
    }

    public function test_rejects_revoked_bearer_token(): void
    {
        $this->actingAs($this->createUser(true), 'web');

        $client = $this->publicClient();
        $code = $this->approve($client);

        $response = $this->exchange($client, $code)->assertOk();
        $token = $response->json('access_token');

        $tokenId = $this->parseToken($token)->claims()->get('jti');

        Passport::token()->findOrFail($tokenId)->revoke();

        $request = Request::create(
            '/api/test',
            'GET',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        $this->expectException(AuthenticationException::class);

        app(OauthClientResolver::class)->resolve($request);
    }

    public function test_resolves_oauth_client_id_from_existing_request_attribute(): void
    {
        $clientId = 'test-client-id';

        $request = Request::create('/api/test', 'GET');

        $request->attributes->set(
            EnsureOauthClientIsResourceOwner::OAUTH_CLIENT_ID_SLUG,
            $clientId
        );

        $this->assertSame(
            $clientId,
            app(OauthClientResolver::class)->resolve($request)
        );
    }
}
