<?php

declare(strict_types=1);

use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;
use Sujip\SentDm\Tests\DatabaseTestCase;
use Sujip\SentDm\Tests\TestCase;
use Sujip\SentDm\Tests\WebhookTestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(WebhookTestCase::class)->in('Webhooks');
uses(DatabaseTestCase::class)->in('Database');

/**
 * Create a partial SentManager mock with connection() stubbed.
 * Used by command tests to avoid real SDK/HTTP calls.
 */
function mockSentManager(?Sent $driver = null): SentManager
{
    $manager = Mockery::mock(SentManager::class)->makePartial();
    $manager->shouldReceive('getDefaultDriver')->andReturn('default');
    $manager->shouldReceive('connection')->andReturn($driver ?? Mockery::mock(Sent::class));

    return $manager;
}
