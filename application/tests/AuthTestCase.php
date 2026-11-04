<?php

namespace Tests;

use App\Enums\DatabaseConnection;
use App\Models\User;

abstract class AuthTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The base test case migrates a fresh named SQLite database for every test.
        config()->set('database.default', DatabaseConnection::APPLICATION);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
        config()->set('database.connections.'.DatabaseConnection::APPLICATION.'.encryption_key', base64_encode(str_repeat('a', 32)));
    }

    protected function createUser(bool $admin = false): User
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $user->forceFill(['is_admin' => $admin])->save();

        return $user->fresh();
    }
}
