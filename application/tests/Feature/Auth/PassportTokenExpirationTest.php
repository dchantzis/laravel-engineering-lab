<?php

namespace Tests\Feature\Auth;

use App\Providers\AuthServiceProvider;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\PassportTestCase;

class PassportTokenExpirationTest extends PassportTestCase
{
    public function test_configured_access_token_lifetime_is_applied_to_signed_token(): void
    {
        config()->set('auth.passport.tokens_expire_in_hours', 2);
        (new AuthServiceProvider($this->app))->boot();
        $start = time();

        $token = $this->userToken($this->createUser(true));

        $claims = $this->parseToken($token)->claims();
        $this->assertGreaterThanOrEqual($start + 7200, $claims->get('exp')->getTimestamp());
        $this->assertLessThanOrEqual(time() + 7200, $claims->get('exp')->getTimestamp());
        $record = Passport::token()->findOrFail($claims->get('jti'));
        $this->assertSame($claims->get('exp')->getTimestamp(), $record->expires_at->getTimestamp());
    }

    public function test_configured_refresh_token_lifetime_is_applied_to_issued_refresh_token(): void
    {
        config()->set('auth.passport.refresh_tokens_expire_in_hours', 5);
        (new AuthServiceProvider($this->app))->boot();
        $admin = $this->createUser(true);
        $this->actingAs($admin, 'web');
        $client = $this->publicClient();
        $code = $this->approve($client);
        $start = time();

        $response = $this->exchange($client, $code)->assertOk();

        $this->assertNotEmpty($response->json('refresh_token'));
        $tokenId = $this->parseToken($response->json('access_token'))->claims()->get('jti');
        $refresh = Passport::refreshToken()->newQuery()->where('access_token_id', $tokenId)->sole();
        $this->assertGreaterThanOrEqual($start + 18000, $refresh->expires_at->getTimestamp());
        $this->assertLessThanOrEqual(time() + 18000, $refresh->expires_at->getTimestamp());
        $this->assertFalse($refresh->revoked);
    }

    public function test_configured_personal_access_token_lifetime_is_applied_to_signed_token(): void
    {
        config()->set('auth.passport.personal_tokens_expire_in_hours', 7);
        (new AuthServiceProvider($this->app))->boot();
        $admin = $this->createUser(true);
        Client::factory()->asPersonalAccessTokenClient()->create(['provider' => 'users']);
        $start = time();

        $result = $admin->createToken('expiration-test');

        $claims = $this->parseToken($result->accessToken)->claims();
        $this->assertSame((string) $admin->id, $claims->get('sub'));
        $this->assertGreaterThanOrEqual($start + 25200, $claims->get('exp')->getTimestamp());
        $this->assertLessThanOrEqual(time() + 25200, $claims->get('exp')->getTimestamp());
        $this->assertSame($claims->get('exp')->getTimestamp(), $result->token->expires_at->getTimestamp());
    }
}
