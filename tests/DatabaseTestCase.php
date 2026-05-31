<?php

declare(strict_types=1);

namespace Sujip\SentDm\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
