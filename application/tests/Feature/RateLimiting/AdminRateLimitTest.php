<?php

namespace Tests\Feature\RateLimiting;

use Tests\PassportTestCase;

class AdminRateLimitTest extends PassportTestCase
{
    public function test_admin_requests_are_rate_limited(): void
    {
        config()->set('rate-limiting.enabled', true);
        config()->set('rate-limiting.api.admin', 2);

        $admin = $this->createUser(true);
        $this->actingAs($admin, 'api');

        $this->getJson('/api/admin/test')->assertOk();
        $this->getJson('/api/admin/test')->assertOk();

        $this->getJson('/api/admin/test')->assertStatus(429);
    }

    public function test_administrators_have_independent_rate_limits(): void
    {
        config()->set('rate-limiting.enabled', true);
        config()->set('rate-limiting.api.admin', 2);

        $firstAdmin = $this->createUser(true);
        $secondAdmin = $this->createUser(true);

        $this->actingAs($firstAdmin, 'api');

        $this->getJson('/api/admin/test')->assertOk();
        $this->getJson('/api/admin/test')->assertOk();
        $this->getJson('/api/admin/test')->assertStatus(429);

        $this->actingAs($secondAdmin, 'api');

        $this->getJson('/api/admin/test')->assertOk();
        $this->getJson('/api/admin/test')->assertOk();
        $this->getJson('/api/admin/test')->assertStatus(429);
    }

    public function test_admin_rate_limiting_can_be_disabled(): void
    {
        config()->set('rate-limiting.enabled', false);
        config()->set('rate-limiting.api.admin', 2);

        $admin = $this->createUser(true);
        $this->actingAs($admin, 'api');

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/admin/test')->assertOk();
        }
    }
}
