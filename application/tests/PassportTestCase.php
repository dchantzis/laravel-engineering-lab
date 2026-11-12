<?php

namespace Tests;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;

abstract class PassportTestCase extends AuthTestCase
{
    protected const string REDIRECT_URI = 'https://client.example.test/callback';

    protected const string VERIFIER = 'test-verifier-abcdefghijklmnopqrstuvwxyz-0123456789';

    private bool $clientTokenKeysConfigured = false;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        config()->set('passport.private_key', $privateKey);
        config()->set('passport.public_key', $details['key']);
    }

    protected function publicClient(): Client
    {
        return Client::factory()->asPublic()->create(['redirect_uris' => [self::REDIRECT_URI]]);
    }

    protected function authorizationUrl(Client $client): string
    {
        return '/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'state' => 'test-state',
            'prompt' => 'consent',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    protected function consent(Client $client): TestResponse
    {
        return $this->get($this->authorizationUrl($client))->assertOk()
            ->assertViewIs('auth.oauth-authorize');
    }

    protected function approve(Client $client): string
    {
        $consent = $this->consent($client);
        $response = $this->post('/oauth/authorize', [
            'client_id' => $client->id,
            'state' => 'test-state',
            'auth_token' => $consent->viewData('authToken'),
        ])->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('test-state', $query['state']);
        $this->assertArrayHasKey('code', $query);
        $this->assertNotSame('', $query['code']);

        return $query['code'];
    }

    protected function exchange(Client $client, string $code, ?string $verifier = self::VERIFIER): TestResponse
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $code,
        ];
        if ($verifier !== null) {
            $payload['code_verifier'] = $verifier;
        }

        return $this->postJson('/oauth/token', $payload);
    }

    protected function userToken(User $user): string
    {
        $this->actingAs($user, 'web');
        $client = $this->publicClient();
        $code = $this->approve($client);
        $response = $this->exchange($client, $code)->assertOk();
        $token = $response->json('access_token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->clearSessionAuthentication();

        return $token;
    }

    protected function clearSessionAuthentication(): void
    {
        $this->app['auth']->guard('web')->logout();
        $this->app['session']->flush();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    protected function parseToken(string $token): UnencryptedToken
    {
        $parsed = (new Parser(new JoseEncoder))->parse($token);
        $this->assertInstanceOf(UnencryptedToken::class, $parsed);

        return $parsed;
    }

    protected function issueClientToken(Client $client): string
    {
        if (! $this->clientTokenKeysConfigured) {
            $key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            $this->assertNotFalse($key);
            $this->assertTrue(openssl_pkey_export($key, $privateKey));

            $details = openssl_pkey_get_details($key);
            $this->assertIsArray($details);

            config()->set('passport.private_key', $privateKey);
            config()->set('passport.public_key', $details['key']);

            $this->clientTokenKeysConfigured = true;
        }

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ])->assertOk();

        $token = $tokenResponse->json('access_token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }
}
