<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
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

/**
 * @param  array<string, mixed>|list<mixed>  $data
 * @return array{0: object{headers: ?array<string, mixed>, body: ?string, uri: ?string}, 1: Sent}
 */
function capturedSentHeaders(array $data = []): array
{
    $captured = new class
    {
        /** @var array<string, mixed>|null */
        public ?array $headers = null;

        public ?string $body = null;

        public ?string $uri = null;
    };

    $responseBody = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 't', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($captured, $responseBody) implements ClientInterface
    {
        public function __construct(private object $cap, private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->cap->headers = $r->getHeaders();
            $this->cap->body = (string) $r->getBody();
            $this->cap->uri = (string) $r->getUri();

            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(client: new Client(apiKey: 'test', requestOptions: $opts));

    return [$captured, $sent];
}

/**
 * Build a Sent driver backed by a test HTTP transport that returns a given
 * response body for every call.
 *
 * @param  array<string, mixed>  $data
 */
function sentApi(array $data = []): Sent
{
    $body = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($body) implements ClientInterface
    {
        public function __construct(private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return new Sent(new Client(apiKey: 'test', requestOptions: $opts));
}

/**
 * Same as sentApi(), for endpoints whose `data` is a bare JSON array.
 *
 * @param  list<mixed>  $data
 */
function sentApiList(array $data): Sent
{
    $body = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($body) implements ClientInterface
    {
        public function __construct(private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return new Sent(new Client(apiKey: 'test', requestOptions: $opts));
}

/**
 * A complete GET /v3/me response body, every field the account/profile shape has.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fullMeBody(array $overrides = []): array
{
    return array_merge([
        'type' => 'organization',
        'id' => 'acct-1',
        'organization_id' => 'org-1',
        'name' => 'Acme',
        'short_name' => 'ACM',
        'email' => 'a@b.com',
        'icon' => 'https://cdn.sent.dm/icons/acme.png',
        'description' => 'Acme organization account',
        'enable_template_auto_creation_for_sp' => true,
        'created_at' => '2025-01-20T14:00:00+00:00',
        'channels' => [
            'sms' => ['configured' => true, 'phone_number' => '+14155550100'],
            'whatsapp' => ['configured' => true, 'phone_number' => '+14155550100', 'business_name' => 'Acme Corporation'],
            'rcs' => ['configured' => false, 'phone_number' => '+14155550100'],
        ],
        'sending_phone_number' => '+14155550100',
        'sending_phone_number_profile_id' => 'acct-1',
        'status' => 'approved',
        'settings' => [
            'allow_contact_sharing' => false,
            'allow_template_sharing' => false,
            'inherit_contacts' => false,
            'inherit_templates' => false,
            'inherit_tcr_brand' => true,
            'inherit_tcr_campaign' => false,
            'billing_model' => 'organization',
        ],
        'profiles' => [],
    ], $overrides);
}
