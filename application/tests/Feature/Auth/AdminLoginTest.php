<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthTestCase;

class AdminLoginTest extends AuthTestCase
{
    public function test_administrator_login_regenerates_session_and_preserves_intended_redirect(): void
    {
        $admin = $this->createUser(true);
        $this->withSession(['url.intended' => '/oauth/authorize', 'marker' => 'preserved']);
        $oldSessionId = session()->getId();

        $this->post('/login', ['email' => $admin->email, 'password' => 'test-password'])
            ->assertRedirect('/oauth/authorize')->assertSessionHasNoErrors()
            ->assertSessionHas('marker', 'preserved');

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_invalid_password_is_rejected_without_an_authenticated_session(): void
    {
        $admin = $this->createUser(true);

        $this->from('/login')->post('/login', ['email' => $admin->email, 'password' => 'incorrect'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Invalid credentials.'])
            ->assertSessionHasInput('email', $admin->email)->assertSessionMissing('_old_input.password');

        $this->assertGuest('web');
        $this->assertNull(session($this->app['auth']->guard('web')->getName()));
    }

    public function test_valid_non_admin_credentials_are_rejected_and_session_authentication_is_cleared(): void
    {
        $user = $this->createUser();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'test-password'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Invalid credentials.']);

        $this->assertGuest('web');
        $this->assertNull(session($this->app['auth']->guard('web')->getName()));
        $this->app['auth']->forgetGuards();
        $this->get('/login')->assertOk();
        $this->assertGuest('web');
    }

    #[DataProvider('invalidLoginFields')]
    public function test_login_fields_are_validated(array $input, array $errors): void
    {
        $this->from('/login')->post('/login', $input)
            ->assertRedirect('/login')->assertSessionHasErrors($errors);

        $this->assertGuest('web');
    }

    public static function invalidLoginFields(): array
    {
        return [
            'missing email' => [['password' => 'test-password'], ['email']],
            'missing password' => [['email' => 'admin@example.test'], ['password']],
            'invalid email' => [['email' => 'invalid', 'password' => 'test-password'], ['email']],
            'non-string password' => [['email' => 'admin@example.test', 'password' => ['invalid']], ['password']],
        ];
    }

    public function test_admin_status_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill(['name' => 'Regular User', 'email' => 'regular@example.test', 'password' => 'test-password', 'is_admin' => true]);
        $user->save();

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => false]);
    }
}
