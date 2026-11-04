<?php

namespace Tests\Feature\Auth;

use App\Services\AdminAuthorizationService;
use Tests\AuthTestCase;

class AdminAuthorizationServiceTest extends AuthTestCase
{
    public function test_administrator_is_recognized(): void
    {
        $this->assertTrue(app(AdminAuthorizationService::class)->isAdmin($this->createUser(true)));
    }

    public function test_regular_user_is_rejected(): void
    {
        $this->assertFalse(app(AdminAuthorizationService::class)->isAdmin($this->createUser()));
    }

    public function test_null_user_is_rejected(): void
    {
        $this->assertFalse(app(AdminAuthorizationService::class)->isAdmin(null));
    }
}
