<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Enums\DatabaseConnection;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.laravel_engineering_lab', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $this->artisan('migrate', [
            '--database' => DatabaseConnection::APPLICATION,
            '--force' => true,
        ]);
    }
}
