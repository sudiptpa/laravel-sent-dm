<?php

declare(strict_types=1);

namespace Sujip\SentDm\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sujip\SentDm\SentServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SentServiceProvider::class,
        ];
    }
}
