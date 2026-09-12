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

/**
 * A complete Channels::addRcs() body, all 24 fields Sent.dm requires. Pass overrides
 * (e.g. ['sandbox' => true]) to merge in per-test extras without repeating the whole
 * shape at every call site.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fullRcsBody(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Acme',
        'description' => 'Home services booking agent',
        'agent_use_case' => 'NOTIFICATIONS',
        'brand_name' => 'Acme Home Services',
        'privacy_policy_url' => 'https://example.com/privacy',
        'terms_and_conditions_url' => 'https://example.com/terms',
        'website_url' => 'https://example.com',
        'brand_color' => '#838FB6',
        'logo_url' => 'https://example.com/logo.png',
        'banner_url' => 'https://example.com/banner.png',
        'brand_phone_number' => '+12125550123',
        'customer_support_phone_number' => '+12125550124',
        'brand_email' => 'hello@example.com',
        'customer_support_email' => 'support@example.com',
        'contact_name_and_title' => 'Jane Smith, Head of Marketing',
        'company_ein' => '12-3456789',
        'entity_type' => 'LLC',
        'official_address' => ['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'],
        'brief_company_description' => 'Home services booking platform',
        'opt_in_process_description' => 'Users opt in via the website sign-up form',
        'start_message' => 'Welcome! Reply HELP for help or STOP to unsubscribe.',
        'help_message' => 'For assistance call +12125550123 or visit example.com',
        'stop_message' => 'You have been unsubscribed. Reply START to re-subscribe.',
        'sample_messages' => ['Your order has shipped.'],
    ], $overrides);
}
