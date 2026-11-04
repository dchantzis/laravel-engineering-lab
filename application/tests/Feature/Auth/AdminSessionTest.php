<?php

namespace Tests\Feature\Auth;

use Tests\AuthTestCase;

class AdminSessionTest extends AuthTestCase
{
    public function test_guest_can_view_login_form(): void
    {
        $this->get('/login')->assertOk()->assertViewIs('auth.login')
            ->assertSee('method="POST"', false)->assertSee('name="email"', false)
            ->assertSee('name="password"', false)->assertSee('name="_token"', false);
    }

    public function test_authenticated_admin_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->createUser(true), 'web')->get('/login')->assertRedirect('/');
    }

    public function test_logout_clears_authentication_invalidates_session_and_regenerates_csrf_token(): void
    {
        $admin = $this->createUser(true);
        $this->post('/login', ['email' => $admin->email, 'password' => 'test-password'])->assertRedirect('/');
        $this->withSession(['private_marker' => 'discard-on-logout']);
        $oldSessionId = session()->getId();
        $oldToken = session()->token();
        $this->assertNotEmpty($oldToken);

        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_marker');

        $this->assertGuest('web');
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotEmpty(session()->token());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertNull(session($this->app['auth']->guard('web')->getName()));
        $this->app['auth']->forgetGuards();
        $this->get('/login')->assertOk();
        $this->assertGuest('web');
    }

    public function test_guest_cannot_use_authenticated_logout_route(): void
    {
        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest('web');
    }
}
