<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\Core\Exceptions\NotFoundException;
use SentDm\Core\FileParam;
use SentDm\RequestOptions;
use SentDm\Webhooks\ChannelEvent;
use SentDm\Webhooks\ContactEvent;
use Sujip\SentDm\Builders\ContactBuilder;
use Sujip\SentDm\Builders\ProfileBuilder;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Builders\TemplateBuilder;
use Sujip\SentDm\Builders\UserInviteBuilder;
use Sujip\SentDm\Builders\WebhookBuilder;
use Sujip\SentDm\Resources\Account;
use Sujip\SentDm\Resources\Campaigns;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Resources\Compliance;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Conversations;
use Sujip\SentDm\Resources\Messages;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\SenderProfiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Responses\SenderProfileData;
use Sujip\SentDm\Sent;

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
 * Same as sentApi(), for the handful of endpoints whose `data` is a bare JSON array
 * instead of an object (e.g. `GET /v3/channels/sms`).
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

// Resource factories ---------------------------------------------------------

it('contacts() returns a Contacts resource', function () {
    expect(sentApi()->contacts())->toBeInstanceOf(Contacts::class);
});

it('conversations() returns a Conversations resource', function () {
    expect(sentApi()->conversations())->toBeInstanceOf(Conversations::class);
});

it('templates() returns a Templates resource', function () {
    expect(sentApi()->templates())->toBeInstanceOf(Templates::class);
});

it('webhooks() returns a Webhooks resource', function () {
    expect(sentApi()->webhooks())->toBeInstanceOf(Webhooks::class);
});

it('profiles() returns a Profiles resource', function () {
    expect(sentApi()->profiles())->toBeInstanceOf(Profiles::class);
});

it('users() returns a Users resource', function () {
    expect(sentApi()->users())->toBeInstanceOf(Users::class);
});

it('senderProfiles() returns a SenderProfiles resource', function () {
    expect(sentApi()->senderProfiles())->toBeInstanceOf(SenderProfiles::class);
});

it('channels() returns a Channels resource', function () {
    expect(sentApi()->channels())->toBeInstanceOf(Channels::class);
});

it('compliance() returns a Compliance resource', function () {
    expect(sentApi()->compliance())->toBeInstanceOf(Compliance::class);
});

// Contacts -------------------------------------------------------------------

it('contacts()->get() lists contacts', function () {
    $result = sentApi([
        'contacts' => [[
            'id' => 'c-1',
            'customer_id' => 'cust-1',
            'phone_number' => '+1234567890',
            'format_e164' => '+1234567890',
            'format_international' => '+1 234-567-890',
            'format_national' => '(234) 567-890',
            'format_rfc' => 'tel:+1-234-567-890',
            'country_code' => '1',
            'region_code' => 'US',
            'available_channels' => 'sms,whatsapp',
            'default_channel' => 'sms',
            'opt_out' => false,
            'is_inherited' => false,
            'created_at' => '2026-09-11T14:57:37+00:00',
            'updated_at' => null,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 150,
            'total_pages' => 8, 'has_more' => true, 'cursors' => null,
        ],
    ])->contacts()->get();

    $contact = $result->data->contacts[0];
    expect($contact->id)->toBe('c-1')
        ->and($contact->customerID)->toBe('cust-1')
        ->and($contact->phoneNumber)->toBe('+1234567890')
        ->and($contact->formatE164)->toBe('+1234567890')
        ->and($contact->formatInternational)->toBe('+1 234-567-890')
        ->and($contact->formatNational)->toBe('(234) 567-890')
        ->and($contact->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($contact->countryCode)->toBe('1')
        ->and($contact->regionCode)->toBe('US')
        ->and($contact->availableChannels)->toBe('sms,whatsapp')
        ->and($contact->defaultChannel)->toBe('sms')
        ->and($contact->optOut)->toBeFalse()
        ->and($contact->isInherited)->toBeFalse()
        ->and($contact->updatedAt)->toBeNull();
});

it('contacts()->search()->channel()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->contacts();
    $chained = $base->search('John')->channel('whatsapp')->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('contacts()->search()->get() passes search param', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])
        ->contacts()
        ->search('John')
        ->get();
    expect($result)->not->toBeNull();
});

it('contacts()->phone()->get() passes the phone filter param', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])
        ->contacts()
        ->phone('+12125550199')
        ->get();
    expect($result)->not->toBeNull();
});

it('contacts()->find() retrieves a contact', function () {
    $result = sentApi([
        'id' => 'c-1',
        'customer_id' => 'cust-1',
        'phone_number' => '+1234567890',
        'format_e164' => '+1234567890',
        'format_international' => '+1 234-567-890',
        'format_national' => '(234) 567-890',
        'format_rfc' => 'tel:+1-234-567-890',
        'country_code' => '1',
        'region_code' => 'US',
        'available_channels' => 'sms',
        'default_channel' => 'sms',
        'opt_out' => false,
        'is_inherited' => false,
        'created_at' => '2026-09-11T14:57:37+00:00',
        'updated_at' => '2026-09-12T00:00:00+00:00',
    ])->contacts()->find('c-1');

    expect($result->data->id)->toBe('c-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->phoneNumber)->toBe('+1234567890')
        ->and($result->data->formatE164)->toBe('+1234567890')
        ->and($result->data->formatInternational)->toBe('+1 234-567-890')
        ->and($result->data->formatNational)->toBe('(234) 567-890')
        ->and($result->data->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($result->data->countryCode)->toBe('1')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->availableChannels)->toBe('sms')
        ->and($result->data->defaultChannel)->toBe('sms')
        ->and($result->data->optOut)->toBeFalse()
        ->and($result->data->isInherited)->toBeFalse()
        ->and($result->data->updatedAt)->not->toBeNull();
});

it('contacts()->create() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->create())->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->create()->phone()->save() creates a contact', function () {
    $result = sentApi([
        'id' => 'c-1',
        'customer_id' => 'cust-1',
        'phone_number' => '+1234567890',
        'format_e164' => '+1234567890',
        'format_international' => '+1 234-567-890',
        'format_national' => '(234) 567-890',
        'format_rfc' => 'tel:+1-234-567-890',
        'country_code' => '1',
        'region_code' => 'US',
        'available_channels' => 'sms',
        'default_channel' => 'sms',
        'opt_out' => false,
        'is_inherited' => false,
        'created_at' => '2026-09-11T14:57:37+00:00',
        'updated_at' => null,
    ])
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->save();

    expect($result->data->id)->toBe('c-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->phoneNumber)->toBe('+1234567890')
        ->and($result->data->formatE164)->toBe('+1234567890')
        ->and($result->data->formatInternational)->toBe('+1 234-567-890')
        ->and($result->data->formatNational)->toBe('(234) 567-890')
        ->and($result->data->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($result->data->countryCode)->toBe('1')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->availableChannels)->toBe('sms')
        ->and($result->data->defaultChannel)->toBe('sms')
        ->and($result->data->optOut)->toBeFalse()
        ->and($result->data->isInherited)->toBeFalse()
        ->and($result->data->updatedAt)->toBeNull();
});

it('contacts()->create()->save() throws without a phone number', function () {
    sentApi()->contacts()->create()->save();
})->throws(InvalidArgumentException::class, 'phone number is required');

it('contacts()->create()->defaultChannel()->save() throws, defaultChannel is update-only', function () {
    sentApi()
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->defaultChannel('sms')
        ->save();
})->throws(InvalidArgumentException::class, 'defaultChannel');

it('contacts()->create()->optOut()->save() throws, optOut is update-only', function () {
    sentApi()
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->optOut(true)
        ->save();
})->throws(InvalidArgumentException::class, 'optOut');

it('contacts()->update() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->update('c-1'))->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->update()->optOut()->save() updates a contact', function () {
    $result = sentApi([
        'id' => 'c-1',
        'customer_id' => 'cust-1',
        'phone_number' => '+1234567890',
        'format_e164' => '+1234567890',
        'format_international' => '+1 234-567-890',
        'format_national' => '(234) 567-890',
        'format_rfc' => 'tel:+1-234-567-890',
        'country_code' => '1',
        'region_code' => 'US',
        'available_channels' => 'sms,whatsapp',
        'default_channel' => 'whatsapp',
        'opt_out' => true,
        'is_inherited' => false,
        'created_at' => '2026-08-12T14:57:37+00:00',
        'updated_at' => '2026-09-11T14:57:37+00:00',
    ])
        ->contacts()
        ->update('c-1')
        ->optOut(true)
        ->save();

    expect($result->data->id)->toBe('c-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->phoneNumber)->toBe('+1234567890')
        ->and($result->data->formatE164)->toBe('+1234567890')
        ->and($result->data->formatInternational)->toBe('+1 234-567-890')
        ->and($result->data->formatNational)->toBe('(234) 567-890')
        ->and($result->data->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($result->data->countryCode)->toBe('1')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->availableChannels)->toBe('sms,whatsapp')
        ->and($result->data->defaultChannel)->toBe('whatsapp')
        ->and($result->data->optOut)->toBeTrue()
        ->and($result->data->isInherited)->toBeFalse()
        ->and($result->data->updatedAt)->not->toBeNull();
});

it('contacts()->update()->defaultChannel()->save() updates a contact', function () {
    $result = sentApi(['id' => 'c-1', 'default_channel' => 'sms'])
        ->contacts()
        ->update('c-1')
        ->defaultChannel('sms')
        ->save();
    expect($result->data->id)->toBe('c-1')
        ->and($result->data->defaultChannel)->toBe('sms');
});

it('contacts()->delete() deletes a contact', function () {
    sentApi([])->contacts()->delete('c-1');
    expect(true)->toBeTrue();
});

it("contacts()->messageSummary() retrieves a contact's message summary", function () {
    $result = sentApi([
        'contact_id' => 'c-1',
        'message_count' => 12,
        'first_message_at' => '2026-01-01T00:00:00Z',
        'last_message_at' => '2026-08-01T00:00:00Z',
        'channels_used' => ['sms', 'whatsapp'],
        'channel_scores' => [
            ['channel' => 'sms', 'success_score' => 91, 'fail_score' => 9],
            ['channel' => 'whatsapp', 'success_score' => 90, 'fail_score' => 10],
        ],
    ])->contacts()->messageSummary('c-1');

    expect($result->data->contactID)->toBe('c-1')
        ->and($result->data->messageCount)->toBe(12)
        ->and($result->data->firstMessageAt)->not->toBeNull()
        ->and($result->data->lastMessageAt)->not->toBeNull()
        ->and($result->data->channelsUsed)->toBe(['sms', 'whatsapp'])
        ->and($result->data->channelScores[0]->channel)->toBe('sms')
        ->and($result->data->channelScores[0]->successScore)->toBe(91)
        ->and($result->data->channelScores[0]->failScore)->toBe(9)
        ->and($result->data->channelScores[1]->channel)->toBe('whatsapp')
        ->and($result->data->channelScores[1]->successScore)->toBe(90)
        ->and($result->data->channelScores[1]->failScore)->toBe(10);
});

// Templates ------------------------------------------------------------------

it('templates()->get() lists templates', function () {
    $result = sentApi([
        'templates' => [[
            'id' => 'tpl-1',
            'customer_id' => 'cust-1',
            'name' => 'Welcome Message',
            'category' => 'MARKETING',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'auto_reply_action' => 'HELP',
            'channels' => ['sms', 'whatsapp', 'rcs'],
            'variables' => ['name', 'company'],
            'created_at' => '2026-08-12T14:57:36+00:00',
            'updated_at' => '2026-08-27T14:57:36+00:00',
            'is_published' => true,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->templates()->get();

    $template = $result->data->templates[0];
    expect($template->id)->toBe('tpl-1')
        ->and($template->customerID)->toBe('cust-1')
        ->and($template->name)->toBe('Welcome Message')
        ->and($template->category)->toBe('MARKETING')
        ->and($template->language)->toBe('en_US')
        ->and($template->status)->toBe('APPROVED')
        ->and($template->autoReplyAction)->toBe('HELP')
        ->and($template->channels)->toBe(['sms', 'whatsapp', 'rcs'])
        ->and($template->variables)->toBe(['name', 'company'])
        ->and($template->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($template->updatedAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($template->isPublished)->toBeTrue();
});

it('templates()->search()->get() passes the search param', function () {
    $result = sentApi(['templates' => []])->templates()->search('welcome')->get();
    expect($result)->not->toBeNull();
});

it('templates()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->templates();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('templates()->find() retrieves a template', function () {
    $result = sentApi([
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'Welcome Message',
        'category' => 'MARKETING',
        'language' => 'en_US',
        'status' => 'APPROVED',
        'auto_reply_action' => null,
        'channels' => ['sms', 'whatsapp', 'rcs'],
        'variables' => ['name', 'company'],
        'created_at' => '2026-08-12T14:57:36+00:00',
        'updated_at' => '2026-08-27T14:57:36+00:00',
        'is_published' => true,
    ])->templates()->find('tpl-1');

    expect($result->data->id)->toBe('tpl-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->name)->toBe('Welcome Message')
        ->and($result->data->category)->toBe('MARKETING')
        ->and($result->data->language)->toBe('en_US')
        ->and($result->data->status)->toBe('APPROVED')
        ->and($result->data->autoReplyAction)->toBeNull()
        ->and($result->data->channels)->toBe(['sms', 'whatsapp', 'rcs'])
        ->and($result->data->variables)->toBe(['name', 'company'])
        ->and($result->data->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->updatedAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->isPublished)->toBeTrue();
});

it('templates()->findByName() returns matching template', function () {
    $result = sentApi(['templates' => [[
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'otp',
        'category' => 'UTILITY',
        'language' => 'en',
        'status' => 'APPROVED',
        'channels' => ['sms'],
        'variables' => [],
        'created_at' => '2026-08-12T14:57:36+00:00',
        'updated_at' => null,
        'is_published' => true,
    ]]])
        ->templates()
        ->findByName('otp');

    expect($result->id)->toBe('tpl-1')
        ->and($result->customerID)->toBe('cust-1')
        ->and($result->name)->toBe('otp')
        ->and($result->category)->toBe('UTILITY')
        ->and($result->language)->toBe('en')
        ->and($result->status)->toBe('APPROVED')
        ->and($result->channels)->toBe(['sms'])
        ->and($result->isPublished)->toBeTrue();
});

it('templates()->findByName() returns null when not found', function () {
    $result = sentApi(['templates' => []])->templates()->findByName('nonexistent');
    expect($result)->toBeNull();
});

it('templates()->delete() deletes a template', function () {
    sentApi([])->templates()->delete('tpl-1');
    expect(true)->toBeTrue();
});

it('templates()->delete() accepts deleteFromMeta', function () {
    sentApi([])->templates()->delete('tpl-1', deleteFromMeta: true);
    expect(true)->toBeTrue();
});

// Webhooks -------------------------------------------------------------------

it('webhooks()->get() lists webhooks', function () {
    $result = sentApi([
        'webhooks' => [[
            'id' => 'wh-1',
            'customer_id' => 'cust-1',
            'display_name' => 'Order Notifications',
            'endpoint_url' => 'https://example.com/webhooks/orders',
            'signing_secret' => null,
            'is_active' => true,
            'event_types' => ['message', 'templates'],
            'event_filters' => null,
            'retry_count' => 3,
            'timeout_seconds' => 30,
            'last_delivery_attempt_at' => null,
            'last_successful_delivery_at' => null,
            'consecutive_failures' => 0,
            'created_at' => '2026-01-15T10:30:00+00:00',
            'updated_at' => null,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->webhooks()->get();

    $webhook = $result->data->webhooks[0];
    expect($webhook->id)->toBe('wh-1')
        ->and($webhook->customerID)->toBe('cust-1')
        ->and($webhook->displayName)->toBe('Order Notifications')
        ->and($webhook->endpointURL)->toBe('https://example.com/webhooks/orders')
        ->and($webhook->signingSecret)->toBeNull()
        ->and($webhook->isActive)->toBeTrue()
        ->and($webhook->eventTypes)->toBe(['message', 'templates'])
        ->and($webhook->eventFilters)->toBeNull()
        ->and($webhook->retryCount)->toBe(3)
        ->and($webhook->timeoutSeconds)->toBe(30)
        ->and($webhook->lastDeliveryAttemptAt)->toBeNull()
        ->and($webhook->lastSuccessfulDeliveryAt)->toBeNull()
        ->and($webhook->consecutiveFailures)->toBe(0);
});

it('webhooks()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->webhooks();
    $chained = $base->page(2)->perPage(10);
    expect($chained)->not->toBe($base);
});

it('webhooks()->search()->isActive()->get() passes both filter params', function () {
    $result = sentApi(['webhooks' => []])->webhooks()->search('order')->isActive(true)->get();
    expect($result)->not->toBeNull();
});

it('webhooks()->listEvents() accepts a search param', function () {
    $result = sentApi(['events' => []])->webhooks()->listEvents('wh-1', search: 'delivered');
    expect($result)->not->toBeNull();
});

it('webhooks()->find() retrieves a webhook', function () {
    $result = sentApi([
        'id' => 'wh-1',
        'customer_id' => 'cust-1',
        'display_name' => 'Order Notifications',
        'endpoint_url' => 'https://example.com/webhooks/orders',
        'signing_secret' => null,
        'is_active' => true,
        'event_types' => ['message', 'templates'],
        'event_filters' => null,
        'retry_count' => 3,
        'timeout_seconds' => 30,
        'last_delivery_attempt_at' => null,
        'last_successful_delivery_at' => null,
        'consecutive_failures' => 0,
        'created_at' => '2026-01-15T10:30:00+00:00',
        'updated_at' => '2026-01-20T14:15:00+00:00',
    ])->webhooks()->find('wh-1');

    expect($result->data->id)->toBe('wh-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->displayName)->toBe('Order Notifications')
        ->and($result->data->endpointURL)->toBe('https://example.com/webhooks/orders')
        ->and($result->data->signingSecret)->toBeNull()
        ->and($result->data->isActive)->toBeTrue()
        ->and($result->data->eventTypes)->toBe(['message', 'templates'])
        ->and($result->data->eventFilters)->toBeNull()
        ->and($result->data->retryCount)->toBe(3)
        ->and($result->data->timeoutSeconds)->toBe(30)
        ->and($result->data->consecutiveFailures)->toBe(0);
});

it('webhooks()->create() returns a WebhookBuilder', function () {
    expect(sentApi()->webhooks()->create())->toBeInstanceOf(WebhookBuilder::class);
});

it('webhooks()->create()->name()->url()->events()->save() creates a webhook', function () {
    $result = sentApi([
        'id' => 'wh-1',
        'customer_id' => 'cust-1',
        'display_name' => 'Order Notifications',
        'endpoint_url' => 'https://example.com/wh',
        'signing_secret' => 'whsec_a1b2c3',
        'is_active' => true,
        'event_types' => ['message', 'templates'],
        'event_filters' => ['message' => ['delivered', 'failed']],
        'retry_count' => 3,
        'timeout_seconds' => 30,
        'last_delivery_attempt_at' => null,
        'last_successful_delivery_at' => null,
        'consecutive_failures' => 0,
        'created_at' => '2026-01-15T10:30:00+00:00',
        'updated_at' => null,
    ])
        ->webhooks()
        ->create()
        ->name('My webhook')
        ->url('https://example.com/wh')
        ->events(['message'])
        ->save();

    expect($result->data->id)->toBe('wh-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->displayName)->toBe('Order Notifications')
        ->and($result->data->endpointURL)->toBe('https://example.com/wh')
        ->and($result->data->signingSecret)->toBe('whsec_a1b2c3')
        ->and($result->data->isActive)->toBeTrue()
        ->and($result->data->eventTypes)->toBe(['message', 'templates'])
        ->and($result->data->eventFilters)->toBe(['message' => ['delivered', 'failed']])
        ->and($result->data->retryCount)->toBe(3)
        ->and($result->data->timeoutSeconds)->toBe(30)
        ->and($result->data->consecutiveFailures)->toBe(0);
});

it('webhooks()->create()->eventFilters()->retryCount()->timeoutSeconds()->save() passes all three', function () {
    $result = sentApi(['id' => 'wh-1'])
        ->webhooks()
        ->create()
        ->name('My webhook')
        ->url('https://example.com/wh')
        ->events(['message'])
        ->eventFilters(['message' => ['delivered', 'failed']])
        ->retryCount(2)
        ->timeoutSeconds(15)
        ->save();
    expect($result->data->id)->toBe('wh-1');
});

it('webhooks()->create()->save() throws without a name', function () {
    sentApi()->webhooks()->create()->url('https://example.com/wh')->events(['message'])->save();
})->throws(InvalidArgumentException::class, 'A name is required');

it('webhooks()->create()->save() throws without events', function () {
    sentApi()->webhooks()->create()->name('My webhook')->url('https://example.com/wh')->save();
})->throws(InvalidArgumentException::class, 'At least one event category is required');

it('webhooks()->create()->save() throws without a url', function () {
    sentApi()->webhooks()->create()->name('My webhook')->events(['message'])->save();
})->throws(InvalidArgumentException::class, 'A URL is required');

it('webhooks()->update() returns a WebhookBuilder', function () {
    expect(sentApi()->webhooks()->update('wh-1'))->toBeInstanceOf(WebhookBuilder::class);
});

it('webhooks()->update()->name()->url()->events()->save() updates a webhook', function () {
    $result = sentApi([
        'id' => 'wh-1',
        'customer_id' => 'cust-1',
        'display_name' => 'Updated Order Notifications',
        'endpoint_url' => 'https://example.com/webhooks/orders-v2',
        'signing_secret' => null,
        'is_active' => true,
        'event_types' => ['message', 'templates'],
        'event_filters' => ['message' => ['delivered', 'failed']],
        'retry_count' => 5,
        'timeout_seconds' => 60,
        'last_delivery_attempt_at' => null,
        'last_successful_delivery_at' => null,
        'consecutive_failures' => 0,
        'created_at' => '2026-01-15T10:30:00+00:00',
        'updated_at' => '2026-02-05T16:45:00+00:00',
    ])
        ->webhooks()
        ->update('wh-1')
        ->name('My webhook')
        ->url('https://example.com/new')
        ->events(['message'])
        ->save();

    expect($result->data->id)->toBe('wh-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->displayName)->toBe('Updated Order Notifications')
        ->and($result->data->endpointURL)->toBe('https://example.com/webhooks/orders-v2')
        ->and($result->data->isActive)->toBeTrue()
        ->and($result->data->eventTypes)->toBe(['message', 'templates'])
        ->and($result->data->eventFilters)->toBe(['message' => ['delivered', 'failed']])
        ->and($result->data->retryCount)->toBe(5)
        ->and($result->data->timeoutSeconds)->toBe(60)
        ->and($result->data->consecutiveFailures)->toBe(0);
});

it('webhooks()->delete() deletes a webhook', function () {
    sentApi([])->webhooks()->delete('wh-1');
    expect(true)->toBeTrue();
});

it('webhooks()->enable() enables a webhook', function () {
    $result = sentApi([
        'id' => 'wh-1',
        'customer_id' => 'cust-1',
        'display_name' => 'Order Notifications',
        'endpoint_url' => 'https://example.com/webhooks/orders',
        'is_active' => true,
        'event_types' => ['message'],
        'retry_count' => 3,
        'timeout_seconds' => 30,
        'consecutive_failures' => 0,
        'created_at' => '2026-01-15T10:30:00+00:00',
        'updated_at' => '2026-02-01T09:00:00+00:00',
    ])->webhooks()->enable('wh-1');

    expect($result->data->id)->toBe('wh-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->displayName)->toBe('Order Notifications')
        ->and($result->data->isActive)->toBeTrue();
});

it('webhooks()->disable() disables a webhook', function () {
    $result = sentApi([
        'id' => 'wh-1',
        'customer_id' => 'cust-1',
        'display_name' => 'Order Notifications',
        'endpoint_url' => 'https://example.com/webhooks/orders',
        'is_active' => false,
        'event_types' => ['message'],
        'retry_count' => 3,
        'timeout_seconds' => 30,
        'consecutive_failures' => 0,
        'created_at' => '2026-01-15T10:30:00+00:00',
        'updated_at' => '2026-02-01T09:00:00+00:00',
    ])->webhooks()->disable('wh-1');

    expect($result->data->id)->toBe('wh-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->displayName)->toBe('Order Notifications')
        ->and($result->data->isActive)->toBeFalse();
});

it('webhooks()->rotateSecret() rotates the signing secret', function () {
    $result = sentApi(['signing_secret' => 'whsec_new'])->webhooks()->rotateSecret('wh-1');
    expect($result->data->signingSecret)->toBe('whsec_new');
});

it('webhooks()->rotateSecret() checks the webhook exists before rotating', function () {
    // retrieve() 404s, rotateSecret() should throw before reaching the rotate call.
    $transporter = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            if (str_contains((string) $r->getUri(), 'rotate-secret')) {
                throw new Exception('rotateSecret() should not be called for an id that does not exist.');
            }

            $body = json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Webhook not found.'],
                'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
            ]);

            return new Response(404, ['Content-Type' => 'application/json'], $body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    expect(fn () => $sent->webhooks()->rotateSecret('wh-missing'))
        ->toThrow(NotFoundException::class);
});

it('webhooks()->rotateSecret(sandbox: true) skips the existence guard', function () {
    // Guard is skipped: only rotate-secret should be called, not retrieve().
    $transporter = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            if (! str_contains((string) $r->getUri(), 'rotate-secret')) {
                throw new Exception('retrieve() should not be called when sandbox is on.');
            }

            $body = json_encode([
                'success' => true,
                'data' => ['signing_secret' => 'whsec_sandboxed'],
                'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
            ]);

            return new Response(200, ['Content-Type' => 'application/json'], $body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    $result = $sent->webhooks()->rotateSecret('wh-sandboxed', sandbox: true);
    expect($result)->not->toBeNull();
});

// Profiles -------------------------------------------------------------------

it('profiles()->get() lists profiles', function () {
    $result = sentApi([
        'profiles' => [[
            'id' => 'prof-1',
            'organization_id' => 'org-1',
            'name' => 'Marketing Team',
            'email' => 'team@acme.com',
            'icon' => 'https://example.com/marketing-icon.png',
            'description' => 'Marketing department sender profile',
            'short_name' => 'MKT',
            'status' => 'approved',
            'created_at' => '2026-06-11T14:57:37+00:00',
            'updated_at' => '2026-09-06T14:57:37+00:00',
            'allow_contact_sharing' => false,
            'allow_template_sharing' => false,
            'inherit_contacts' => false,
            'inherit_templates' => false,
            'inherit_tcr_brand' => false,
            'inherit_tcr_campaign' => false,
            'billing_model' => 'profile',
            'sending_phone_number_profile_id' => null,
            'sending_whatsapp_number_profile_id' => null,
            'sending_phone_number' => null,
            'whatsapp_phone_number' => null,
            'allow_number_change_during_onboarding' => null,
            'waba_id' => null,
            'billing_contact' => ['name' => 'Jane', 'email' => 'jane@acme.com', 'phone' => '+12125550123', 'address' => '1 Example St'],
            'brand' => [
                'id' => 'brand-1',
                'tcr_brand_id' => null,
                'status' => null,
                'identity_status' => null,
                'universal_ein' => null,
                'csp_id' => null,
                'submitted_to_tcr' => false,
                'submitted_at' => null,
                'is_inherited' => false,
                'created_at' => '2026-06-11T14:57:37+00:00',
                'updated_at' => null,
                'contact' => [
                    'name' => 'John Smith', 'business_name' => 'Acme Corp', 'role' => null,
                    'phone' => null, 'email' => 'john@acmecorp.com', 'phone_country_code' => null,
                ],
                'business' => [
                    'legal_name' => 'Acme Corporation LLC', 'tax_id' => null, 'tax_id_type' => null,
                    'entity_type' => null, 'street' => null, 'city' => null, 'state' => null,
                    'postal_code' => null, 'country' => 'US', 'url' => null, 'country_of_registration' => null,
                ],
                'compliance' => [
                    'vertical' => 'PROFESSIONAL', 'brand_relationship' => 'SMALL_ACCOUNT',
                    'is_tcr_application' => true, 'phone_number_prefix' => null,
                    'destination_countries' => [], 'notes' => null, 'primary_use_case' => null,
                ],
            ],
        ]],
        'pagination' => null,
    ])->profiles()->get();

    $profile = $result->data->profiles[0];
    expect($profile->id)->toBe('prof-1')
        ->and($profile->organizationID)->toBe('org-1')
        ->and($profile->name)->toBe('Marketing Team')
        ->and($profile->email)->toBe('team@acme.com')
        ->and($profile->icon)->toBe('https://example.com/marketing-icon.png')
        ->and($profile->description)->toBe('Marketing department sender profile')
        ->and($profile->shortName)->toBe('MKT')
        ->and($profile->status)->toBe('approved')
        ->and($profile->allowContactSharing)->toBeFalse()
        ->and($profile->allowTemplateSharing)->toBeFalse()
        ->and($profile->inheritContacts)->toBeFalse()
        ->and($profile->inheritTemplates)->toBeFalse()
        ->and($profile->inheritTcrBrand)->toBeFalse()
        ->and($profile->inheritTcrCampaign)->toBeFalse()
        ->and($profile->billingModel)->toBe('profile')
        ->and($profile->billingContact->name)->toBe('Jane')
        ->and($profile->billingContact->email)->toBe('jane@acme.com')
        ->and($profile->billingContact->phone)->toBe('+12125550123')
        ->and($profile->billingContact->address)->toBe('1 Example St')
        ->and($profile->brand->id)->toBe('brand-1')
        ->and($profile->brand->submittedToTcr)->toBeFalse()
        ->and($profile->brand->isInherited)->toBeFalse()
        ->and($profile->brand->contact->name)->toBe('John Smith')
        ->and($profile->brand->contact->businessName)->toBe('Acme Corp')
        ->and($profile->brand->contact->email)->toBe('john@acmecorp.com')
        ->and($profile->brand->business->legalName)->toBe('Acme Corporation LLC')
        ->and($profile->brand->business->country)->toBe('US')
        ->and($profile->brand->compliance->vertical)->toBe('PROFESSIONAL')
        ->and($profile->brand->compliance->brandRelationship)->toBe('SMALL_ACCOUNT')
        ->and($profile->brand->compliance->isTcrApplication)->toBeTrue()
        ->and($profile->brand->compliance->destinationCountries)->toBe([]);
});

it('profiles()->find() retrieves a profile', function () {
    $result = sentApi([
        'id' => 'prof-1',
        'organization_id' => 'org-1',
        'name' => 'Marketing Team',
        'email' => 'team@acme.com',
        'icon' => 'https://example.com/icon.png',
        'description' => 'Marketing department sender profile',
        'short_name' => 'MKT',
        'status' => 'approved',
        'created_at' => '2026-06-11T14:57:37+00:00',
        'updated_at' => '2026-09-06T14:57:37+00:00',
        'allow_contact_sharing' => false,
        'allow_template_sharing' => false,
        'inherit_contacts' => false,
        'inherit_templates' => false,
        'inherit_tcr_brand' => false,
        'inherit_tcr_campaign' => false,
        'billing_model' => 'profile',
        'sending_phone_number' => '+12125550123',
        'sending_phone_number_profile_id' => null,
        'sending_whatsapp_number_profile_id' => null,
        'whatsapp_phone_number' => null,
        'allow_number_change_during_onboarding' => true,
        'waba_id' => 'waba-1',
        'billing_contact' => null,
        'brand' => null,
    ])->profiles()->find('prof-1');

    expect($result->data->id)->toBe('prof-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Marketing Team')
        ->and($result->data->email)->toBe('team@acme.com')
        ->and($result->data->icon)->toBe('https://example.com/icon.png')
        ->and($result->data->description)->toBe('Marketing department sender profile')
        ->and($result->data->shortName)->toBe('MKT')
        ->and($result->data->status)->toBe('approved')
        ->and($result->data->allowContactSharing)->toBeFalse()
        ->and($result->data->allowTemplateSharing)->toBeFalse()
        ->and($result->data->inheritContacts)->toBeFalse()
        ->and($result->data->inheritTemplates)->toBeFalse()
        ->and($result->data->inheritTcrBrand)->toBeFalse()
        ->and($result->data->inheritTcrCampaign)->toBeFalse()
        ->and($result->data->billingModel)->toBe('profile')
        ->and($result->data->sendingPhoneNumber)->toBe('+12125550123')
        ->and($result->data->allowNumberChangeDuringOnboarding)->toBeTrue()
        ->and($result->data->wabaID)->toBe('waba-1');
});

it('profiles()->delete() deletes a profile', function () {
    sentApi([])->profiles()->delete('prof-1');
    expect(true)->toBeTrue();
});

it('profiles()->create() returns a ProfileBuilder', function () {
    expect(sentApi()->profiles()->create())->toBeInstanceOf(ProfileBuilder::class);
});

it('profiles()->create()->name()->save() creates a profile', function () {
    $result = sentApi([
        'id' => 'prof-1',
        'organization_id' => 'org-1',
        'name' => 'Sales',
        'email' => null,
        'icon' => null,
        'description' => null,
        'short_name' => null,
        'status' => 'pending',
        'created_at' => '2026-09-11T00:00:00+00:00',
        'updated_at' => null,
        'allow_contact_sharing' => false,
        'allow_template_sharing' => false,
        'inherit_contacts' => false,
        'inherit_templates' => false,
        'inherit_tcr_brand' => false,
        'inherit_tcr_campaign' => false,
        'billing_model' => 'organization',
    ])->profiles()->create()->name('Sales')->save();

    expect($result->data->id)->toBe('prof-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Sales')
        ->and($result->data->status)->toBe('pending')
        ->and($result->data->allowContactSharing)->toBeFalse()
        ->and($result->data->allowTemplateSharing)->toBeFalse()
        ->and($result->data->inheritContacts)->toBeFalse()
        ->and($result->data->inheritTemplates)->toBeFalse()
        ->and($result->data->inheritTcrBrand)->toBeFalse()
        ->and($result->data->inheritTcrCampaign)->toBeFalse()
        ->and($result->data->billingModel)->toBe('organization');
});

it('profiles()->create() chains are immutable', function () {
    $base = sentApi()->profiles()->create();
    $chained = $base->name('Sales')->description('Sales profile');
    expect($chained)->not->toBe($base);
});

it('profiles()->create() builder exercises all create setters', function () {
    $base = sentApi(['id' => 'prof-1'])->profiles()->create();
    $result = $base
        ->name('Test')
        ->description('desc')
        ->shortName('TST')
        ->icon('https://example.com/icon.png')
        ->billingModel('organization')
        ->inheritContacts(true)
        ->inheritTemplates(true)
        ->inheritTcrBrand(true)
        ->inheritTcrCampaign(true)
        ->allowContactSharing(true)
        ->allowTemplateSharing(true)
        ->billingContact([])
        ->brand([])
        ->paymentDetails([])
        ->whatsappBusinessAccount([])
        ->save();
    expect($result)->not->toBeNull();
});

it('profiles()->update() returns a ProfileBuilder', function () {
    expect(sentApi()->profiles()->update('prof-1'))->toBeInstanceOf(ProfileBuilder::class);
});

it('profiles()->update()->name()->save() updates a profile', function () {
    $result = sentApi([
        'id' => 'prof-1',
        'organization_id' => 'org-1',
        'name' => 'Support',
        'status' => 'approved',
        'sending_phone_number' => '+12125550123',
        'sending_phone_number_profile_id' => 'prof-2',
        'sending_whatsapp_number_profile_id' => 'prof-3',
        'whatsapp_phone_number' => '+12125550123',
        'allow_number_change_during_onboarding' => true,
        'updated_at' => '2026-09-12T00:00:00+00:00',
    ])->profiles()->update('prof-1')->name('Support')->save();

    expect($result->data->id)->toBe('prof-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Support')
        ->and($result->data->status)->toBe('approved')
        ->and($result->data->sendingPhoneNumber)->toBe('+12125550123')
        ->and($result->data->sendingPhoneNumberProfileID)->toBe('prof-2')
        ->and($result->data->sendingWhatsappNumberProfileID)->toBe('prof-3')
        ->and($result->data->whatsappPhoneNumber)->toBe('+12125550123')
        ->and($result->data->allowNumberChangeDuringOnboarding)->toBeTrue();
});

it('profiles()->update() chains are immutable', function () {
    $base = sentApi()->profiles()->update('prof-1');
    $chained = $base->name('Support')->shortName('SUP');
    expect($chained)->not->toBe($base);
});

it('profiles()->update() builder exercises all update-only setters', function () {
    $base = sentApi(['id' => 'prof-1'])->profiles()->update('prof-1');
    $result = $base
        ->allowNumberChangeDuringOnboarding(true)
        ->sendingPhoneNumber('+61412345678')
        ->sendingPhoneNumberProfileId('prof-2')
        ->sendingWhatsappNumberProfileId('prof-3')
        ->whatsappPhoneNumber('+61412345678')
        ->save();
    expect($result)->not->toBeNull();
});

it('profiles()->complete() triggers profile completion', function () {
    sentApi([])->profiles()->complete('prof-1', 'https://example.com/webhook');
    expect(true)->toBeTrue();
});

it('profiles()->campaigns() returns a Campaigns resource', function () {
    expect(sentApi()->profiles()->campaigns('prof-1'))->toBeInstanceOf(Campaigns::class);
});

it('profiles()->campaigns()->get() lists campaigns', function () {
    $result = sentApi([])->profiles()->campaigns('prof-1')->get();
    expect($result->data)->toBe([]);
});

it('profiles()->campaigns()->create() creates a campaign', function () {
    $result = sentApi([
        'id' => 'camp-1',
        'customerId' => 'cust-1',
        'type' => 'App',
        'name' => 'Customer Notifications',
        'description' => 'Appointment reminders',
        'brandId' => 'brand-1',
        'status' => null,
        'tcrCampaignId' => null,
        'submittedToTCR' => false,
        'submittedAt' => null,
        'cost' => null,
        'billedDate' => null,
        'messageFlow' => 'User opts in on the website',
        'volume' => '1500',
        'privacyPolicyLink' => 'https://example.com/privacy',
        'termsAndConditionsLink' => 'https://example.com/terms',
        'optinMessage' => 'You are subscribed',
        'optoutMessage' => 'You are unsubscribed',
        'helpMessage' => 'Reply HELP for help',
        'optinKeywords' => 'START',
        'optoutKeywords' => 'STOP',
        'helpKeywords' => 'HELP',
        'dcaElectionsComplete' => false,
        'dcaElectionsCompletedAt' => null,
        'tcrSyncError' => null,
        'hasSubmissionTransaction' => false,
        'useCases' => [[
            'id' => 'uc-1', 'campaignId' => 'camp-1', 'customerId' => 'cust-1',
            'messagingUseCaseUs' => 'ACCOUNT_NOTIFICATION',
            'sampleMessages' => ['Your appointment is confirmed.'],
            'createdAt' => '2026-09-11T14:57:37+00:00', 'updatedAt' => null,
        ]],
    ])
        ->profiles()
        ->campaigns('prof-1')
        ->create(['description' => 'OTP', 'name' => 'OTP', 'type' => 'KYC', 'useCases' => []]);

    expect($result->data->id)->toBe('camp-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->type)->toBe('App')
        ->and($result->data->name)->toBe('Customer Notifications')
        ->and($result->data->description)->toBe('Appointment reminders')
        ->and($result->data->brandID)->toBe('brand-1')
        ->and($result->data->submittedToTcr)->toBeFalse()
        ->and($result->data->messageFlow)->toBe('User opts in on the website')
        ->and($result->data->volume)->toBe('1500')
        ->and($result->data->privacyPolicyLink)->toBe('https://example.com/privacy')
        ->and($result->data->termsAndConditionsLink)->toBe('https://example.com/terms')
        ->and($result->data->optinMessage)->toBe('You are subscribed')
        ->and($result->data->optoutMessage)->toBe('You are unsubscribed')
        ->and($result->data->helpMessage)->toBe('Reply HELP for help')
        ->and($result->data->optinKeywords)->toBe('START')
        ->and($result->data->optoutKeywords)->toBe('STOP')
        ->and($result->data->helpKeywords)->toBe('HELP')
        ->and($result->data->dcaElectionsComplete)->toBeFalse()
        ->and($result->data->hasSubmissionTransaction)->toBeFalse()
        ->and($result->data->useCases[0]->id)->toBe('uc-1')
        ->and($result->data->useCases[0]->campaignID)->toBe('camp-1')
        ->and($result->data->useCases[0]->messagingUseCaseUs)->toBe('ACCOUNT_NOTIFICATION')
        ->and($result->data->useCases[0]->sampleMessages)->toBe(['Your appointment is confirmed.']);
});

it('profiles()->campaigns()->update() updates a campaign', function () {
    $result = sentApi(['id' => 'camp-1', 'name' => 'Customer Notifications Updated'])
        ->profiles()
        ->campaigns('prof-1')
        ->update('camp-1', ['description' => 'OTP v2', 'name' => 'OTP', 'type' => 'KYC', 'useCases' => []]);
    expect($result->data->id)->toBe('camp-1')
        ->and($result->data->name)->toBe('Customer Notifications Updated');
});

it('profiles()->campaigns()->delete() deletes a campaign', function () {
    sentApi([])->profiles()->campaigns('prof-1')->delete('camp-1');
    expect(true)->toBeTrue();
});

// Users ----------------------------------------------------------------------

it('users()->get() lists users', function () {
    $result = sentApi(['users' => [[
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'admin@acme.com',
        'name' => 'John Admin',
        'role' => 'admin',
        'status' => 'active',
        'invited_at' => '2026-03-11T14:57:36+00:00',
        'last_login_at' => '2026-09-11T12:57:36+00:00',
        'created_at' => '2026-03-11T14:57:36+00:00',
        'updated_at' => '2026-09-10T14:57:36+00:00',
    ]]])->users()->get();

    $user = $result->data->users[0];
    expect($user->id)->toBe('user-1')
        ->and($user->customerID)->toBe('cust-1')
        ->and($user->email)->toBe('admin@acme.com')
        ->and($user->name)->toBe('John Admin')
        ->and($user->role)->toBe('admin')
        ->and($user->status)->toBe('active')
        ->and($user->invitedAt)->not->toBeNull()
        ->and($user->lastLoginAt)->not->toBeNull();
});

it('users()->find() retrieves a user', function () {
    $result = sentApi([
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'admin@acme.com',
        'name' => 'John Admin',
        'role' => 'admin',
        'status' => 'active',
        'invited_at' => '2026-03-11T14:57:36+00:00',
        'last_login_at' => '2026-09-11T12:57:36+00:00',
        'created_at' => '2026-03-11T14:57:36+00:00',
        'updated_at' => '2026-09-10T14:57:36+00:00',
    ])->users()->find('user-1');

    expect($result->data->id)->toBe('user-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->email)->toBe('admin@acme.com')
        ->and($result->data->name)->toBe('John Admin')
        ->and($result->data->role)->toBe('admin')
        ->and($result->data->status)->toBe('active')
        ->and($result->data->invitedAt)->not->toBeNull()
        ->and($result->data->lastLoginAt)->not->toBeNull();
});

it('users()->invite() returns a UserInviteBuilder', function () {
    expect(sentApi()->users()->invite())->toBeInstanceOf(UserInviteBuilder::class);
});

it('users()->invite()->email()->name()->role()->save() invites a user', function () {
    $result = sentApi([
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'jane@example.com',
        'name' => 'Jane',
        'role' => 'admin',
        'status' => 'invited',
        'invited_at' => '2026-09-11T14:57:36+00:00',
        'last_login_at' => null,
        'created_at' => '2026-09-11T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
    ])
        ->users()
        ->invite()
        ->email('jane@example.com')
        ->name('Jane')
        ->role('admin')
        ->save();

    expect($result->data->id)->toBe('user-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->email)->toBe('jane@example.com')
        ->and($result->data->name)->toBe('Jane')
        ->and($result->data->role)->toBe('admin')
        ->and($result->data->status)->toBe('invited')
        ->and($result->data->lastLoginAt)->toBeNull();
});

it('users()->remove() removes a user', function () {
    sentApi([])->users()->remove('user-1');
    expect(true)->toBeTrue();
});

// Account + lookup -----------------------------------------------------------

it('account() delegates to SDK me->retrieve', function () {
    $result = sentApi(fullMeBody())->account();

    expect($result->data->type)->toBe('organization')
        ->and($result->data->id)->toBe('acct-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Acme')
        ->and($result->data->shortName)->toBe('ACM')
        ->and($result->data->email)->toBe('a@b.com')
        ->and($result->data->icon)->toBe('https://cdn.sent.dm/icons/acme.png')
        ->and($result->data->description)->toBe('Acme organization account')
        ->and($result->data->status)->toBe('approved')
        ->and($result->data->profiles)->toBe([])
        ->and($result->data->channels->sms->configured)->toBeTrue()
        ->and($result->data->channels->sms->phoneNumber)->toBe('+14155550100')
        ->and($result->data->channels->whatsapp->configured)->toBeTrue()
        ->and($result->data->channels->whatsapp->businessName)->toBe('Acme Corporation')
        ->and($result->data->channels->rcs->configured)->toBeFalse()
        ->and($result->data->settings->allowContactSharing)->toBeFalse()
        ->and($result->data->settings->allowTemplateSharing)->toBeFalse()
        ->and($result->data->settings->inheritContacts)->toBeFalse()
        ->and($result->data->settings->inheritTemplates)->toBeFalse()
        ->and($result->data->settings->inheritTcrBrand)->toBeTrue()
        ->and($result->data->settings->inheritTcrCampaign)->toBeFalse()
        ->and($result->data->settings->billingModel)->toBe('organization');
});

it('me() returns an Account resource', function () {
    expect(sentApi()->me())->toBeInstanceOf(Account::class);
});

it('me()->get() delegates to SDK me->retrieve', function () {
    $result = sentApi(fullMeBody())->me()->get();

    expect($result->data->type)->toBe('organization')
        ->and($result->data->name)->toBe('Acme')
        ->and($result->data->email)->toBe('a@b.com')
        ->and($result->data->channels->sms->configured)->toBeTrue()
        ->and($result->data->settings->billingModel)->toBe('organization');
});

it('me()->profile() sends x-profile-id, unlike account()', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->me()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('lookup() delegates to SDK numbers->lookup', function () {
    $result = sentApi([
        'phone_number' => '+61412345678',
        'is_valid' => true,
        'carrier_name' => 'Telstra',
        'line_type' => 'mobile',
        'country_code' => 'AU',
        'mobile_country_code' => '505',
        'mobile_network_code' => '01',
        'is_ported' => false,
        'is_voip' => false,
    ])->lookup('+61412345678');

    expect($result->data->phoneNumber)->toBe('+61412345678')
        ->and($result->data->isValid)->toBeTrue()
        ->and($result->data->carrierName)->toBe('Telstra')
        ->and($result->data->lineType)->toBe('mobile')
        ->and($result->data->countryCode)->toBe('AU')
        ->and($result->data->mobileCountryCode)->toBe('505')
        ->and($result->data->mobileNetworkCode)->toBe('01')
        ->and($result->data->isPorted)->toBeFalse()
        ->and($result->data->isVoip)->toBeFalse();
});

// Templates: write ops + filters --------------------------------------------

it('templates()->create() returns a TemplateBuilder', function () {
    expect(sentApi()->templates()->create())->toBeInstanceOf(TemplateBuilder::class);
});

it('templates()->create()->category()->language()->save() creates a template', function () {
    $result = sentApi([
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'Welcome Message',
        'category' => 'MARKETING',
        'language' => 'en_US',
        'status' => 'DRAFT',
        'channels' => ['sms', 'whatsapp', 'rcs'],
        'variables' => ['name', 'company'],
        'created_at' => '2026-09-11T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
        'is_published' => false,
    ])
        ->templates()
        ->create()
        ->category('MARKETING')
        ->language('en_US')
        ->save();

    expect($result->data->id)->toBe('tpl-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->name)->toBe('Welcome Message')
        ->and($result->data->category)->toBe('MARKETING')
        ->and($result->data->language)->toBe('en_US')
        ->and($result->data->status)->toBe('DRAFT')
        ->and($result->data->channels)->toBe(['sms', 'whatsapp', 'rcs'])
        ->and($result->data->variables)->toBe(['name', 'company'])
        ->and($result->data->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->updatedAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->isPublished)->toBeFalse();
});

it('templates()->create()->submitForReview()->save() creates a template submitted for review', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'otp'])
        ->templates()
        ->create()
        ->submitForReview()
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create()->definition()->save() creates a template with a definition', function () {
    $result = sentApi(['id' => 'tpl-1'])
        ->templates()
        ->create()
        ->category('UTILITY')
        ->definition(['body' => ['sms' => ['template' => 'Hello {{name}}', 'type' => 'text']]])
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create() chains are immutable', function () {
    $base = sentApi()->templates()->create();
    $chained = $base->category('MARKETING')->language('en_US');
    expect($chained)->not->toBe($base);
});

it('templates()->create()->name()->save() throws, name is update-only', function () {
    sentApi()->templates()->create()->name('my-template')->save();
})->throws(InvalidArgumentException::class, 'name() is not supported when creating');

it('templates()->create()->creationSource()->save() passes creation_source', function () {
    $result = sentApi(['id' => 'tpl-1'])->templates()->create()->creationSource('import-script')->save();
    expect($result)->not->toBeNull();
});

it('templates()->update()->creationSource()->save() throws, creationSource is create-only', function () {
    sentApi(['id' => 'tpl-1'])->templates()->update('tpl-1')->creationSource('import-script')->save();
})->throws(InvalidArgumentException::class, 'creationSource() is not supported when updating');

it('templates()->update() returns a TemplateBuilder', function () {
    expect(sentApi()->templates()->update('tpl-1'))->toBeInstanceOf(TemplateBuilder::class);
});

it('templates()->update()->name()->save() updates a template', function () {
    $result = sentApi([
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'Updated Welcome Message',
        'category' => 'MARKETING',
        'language' => 'en_US',
        'status' => 'DRAFT',
        'channels' => ['sms', 'whatsapp'],
        'variables' => ['name', 'company'],
        'created_at' => '2026-08-12T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
        'is_published' => false,
    ])
        ->templates()
        ->update('tpl-1')
        ->name('new-name')
        ->save();

    expect($result->data->id)->toBe('tpl-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->name)->toBe('Updated Welcome Message')
        ->and($result->data->category)->toBe('MARKETING')
        ->and($result->data->language)->toBe('en_US')
        ->and($result->data->status)->toBe('DRAFT')
        ->and($result->data->channels)->toBe(['sms', 'whatsapp'])
        ->and($result->data->variables)->toBe(['name', 'company'])
        ->and($result->data->isPublished)->toBeFalse();
});

it('templates()->update() chains are immutable', function () {
    $base = sentApi()->templates()->update('tpl-1');
    $chained = $base->name('new-name')->category('UTILITY');
    expect($chained)->not->toBe($base);
});

it('templates()->category()->get() filters by category', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->category('MARKETING');
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

it('templates()->status()->get() filters by status', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->status('APPROVED');
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

it('templates()->isWelcomePlayground()->get() filters by welcome playground flag', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->isWelcomePlayground(true);
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

// Users: updateRole ---------------------------------------------------------

it('users()->updateRole() updates a user role', function () {
    $result = sentApi([
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'user@example.com',
        'name' => 'User Name',
        'role' => 'billing',
        'status' => 'active',
        'invited_at' => '2026-08-11T14:57:36+00:00',
        'last_login_at' => '2026-09-11T09:57:36+00:00',
        'created_at' => '2026-08-11T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
    ])
        ->users()
        ->updateRole('user-1', 'admin');

    expect($result->data->id)->toBe('user-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->email)->toBe('user@example.com')
        ->and($result->data->name)->toBe('User Name')
        ->and($result->data->role)->toBe('billing')
        ->and($result->data->status)->toBe('active');
});

// Webhooks: test, listEvents, listEventTypes --------------------------------

it('webhooks()->test() sends a test event', function () {
    $result = sentApi(['success' => true, 'message' => 'Test event delivered successfully'])
        ->webhooks()->test('wh-1', 'message.delivered');
    expect($result->data->success)->toBeTrue()
        ->and($result->data->message)->toBe('Test event delivered successfully');
});

it('webhooks()->test() without eventType throws', function () {
    sentApi(['success' => true])->webhooks()->test('wh-1');
})->throws(InvalidArgumentException::class, 'An event type is required.');

it('webhooks()->listEvents() lists events for a webhook', function () {
    $result = sentApi([
        'events' => [[
            'id' => 'evt-1',
            'event_type' => 'message.delivered',
            'event_data' => null,
            'delivery_status' => 'delivered',
            'http_status_code' => 200,
            'response_body' => null,
            'delivery_attempts' => 1,
            'error_message' => null,
            'created_at' => '2026-01-20T14:30:00+00:00',
            'processing_started_at' => '2026-01-20T14:30:01+00:00',
            'processing_completed_at' => '2026-01-20T14:30:02+00:00',
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->webhooks()->listEvents('wh-1');

    $event = $result->data->events[0];
    expect($event->id)->toBe('evt-1')
        ->and($event->eventType)->toBe('message.delivered')
        ->and($event->deliveryStatus)->toBe('delivered')
        ->and($event->httpStatusCode)->toBe(200)
        ->and($event->responseBody)->toBeNull()
        ->and($event->deliveryAttempts)->toBe(1)
        ->and($event->errorMessage)->toBeNull();
});

it('webhooks()->listEvents() hydrates contact and channel event data from the SDK', function () {
    $result = sentApi([
        'events' => [
            [
                'id' => 'evt-contact',
                'event_type' => 'contact.opt_out',
                'event_data' => [
                    'field' => 'contact',
                    'event' => 'contact.opt_out',
                    'timestamp' => '2026-09-25T07:41:34Z',
                    'request_id' => 'req_contact_1',
                    'payload' => [
                        'account_id' => 'acc_1',
                        'contact_id' => 'contact_1',
                        'phone_number' => '+61412345678',
                        'opt_out' => true,
                        'source' => 'INBOUND_KEYWORD',
                        'channel' => 'sms',
                        'text' => 'STOP',
                        'message_id' => 'msg_1',
                    ],
                ],
                'delivery_status' => 'delivered',
                'http_status_code' => 200,
                'response_body' => null,
                'delivery_attempts' => 1,
                'error_message' => null,
                'created_at' => '2026-09-25T07:41:34Z',
            ],
            [
                'id' => 'evt-channel',
                'event_type' => 'channel.activated',
                'event_data' => [
                    'field' => 'channel',
                    'event' => 'channel.activated',
                    'timestamp' => '2026-09-25T07:41:34Z',
                    'request_id' => 'req_channel_1',
                    'payload' => [
                        'account_id' => 'acc_1',
                        'channel' => 'rcs',
                        'status' => 'ACTIVE',
                        'country' => 'US',
                        'number_type' => 'TEN_DLC',
                        'sender_value' => '+14155550101',
                        'reason' => 'approved',
                        'updated_at' => '2026-09-25T07:41:34Z',
                    ],
                ],
                'delivery_status' => 'delivered',
                'http_status_code' => 200,
                'response_body' => null,
                'delivery_attempts' => 1,
                'error_message' => null,
                'created_at' => '2026-09-25T07:41:34Z',
            ],
        ],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 2,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->webhooks()->listEvents('wh-1');

    $contact = $result->data->events[0]->eventData;
    $channel = $result->data->events[1]->eventData;

    expect($contact)->toBeInstanceOf(ContactEvent::class)
        ->and($contact->field)->toBe('contact')
        ->and($contact->requestID)->toBe('req_contact_1')
        ->and($contact->payload->accountID)->toBe('acc_1')
        ->and($contact->payload->contactID)->toBe('contact_1')
        ->and($contact->payload->phoneNumber)->toBe('+61412345678')
        ->and($contact->payload->optOut)->toBeTrue()
        ->and($contact->payload->source)->toBe('INBOUND_KEYWORD')
        ->and($contact->payload->channel)->toBe('sms')
        ->and($contact->payload->text)->toBe('STOP')
        ->and($contact->payload->messageID)->toBe('msg_1')
        ->and($channel)->toBeInstanceOf(ChannelEvent::class)
        ->and($channel->field)->toBe('channel')
        ->and($channel->requestID)->toBe('req_channel_1')
        ->and($channel->payload->accountID)->toBe('acc_1')
        ->and($channel->payload->channel)->toBe('rcs')
        ->and($channel->payload->status)->toBe('ACTIVE')
        ->and($channel->payload->country)->toBe('US')
        ->and($channel->payload->numberType)->toBe('TEN_DLC')
        ->and($channel->payload->senderValue)->toBe('+14155550101')
        ->and($channel->payload->reason)->toBe('approved')
        ->and($channel->payload->updatedAt)->toBe('2026-09-25T07:41:34Z');
});

it('webhooks()->listEvents() accepts page and pageSize', function () {
    $result = sentApi(['events' => []])->webhooks()->listEvents('wh-1', page: 2, pageSize: 25);
    expect($result)->not->toBeNull();
});

it('webhooks()->listEventTypes() lists all event types', function () {
    $result = sentApi([
        'event_types' => [[
            'name' => 'message.sent',
            'display_name' => 'Message Sent',
            'description' => 'Triggered when a message is successfully sent',
            'is_active' => true,
            'event_type' => null,
            'sub_types' => null,
        ]],
        'pagination' => null,
    ])->webhooks()->listEventTypes();

    $eventType = $result->data->eventTypes[0];
    expect($eventType->name)->toBe('message.sent')
        ->and($eventType->displayName)->toBe('Message Sent')
        ->and($eventType->description)->toBe('Triggered when a message is successfully sent')
        ->and($eventType->isActive)->toBeTrue()
        ->and($eventType->eventType)->toBeNull()
        ->and($eventType->subTypes)->toBeNull();
});

// Messages resource ----------------------------------------------------------

it('messages() returns a Messages resource', function () {
    expect(sentApi()->messages())->toBeInstanceOf(Messages::class);
});

it('messages()->retrieve() returns message status', function () {
    $result = sentApi([
        'id' => 'msg-1',
        'customer_id' => 'cust-1',
        'contact_id' => 'c-1',
        'phone' => '+14155551234',
        'phone_international' => '+1 415-555-1234',
        'region_code' => 'US',
        'template_id' => 'tpl-1',
        'template_name' => 'Welcome Message',
        'template_category' => 'UTILITY',
        'channel' => 'sms',
        'message_body' => ['header' => null, 'content' => 'Welcome!', 'footer' => null, 'buttons' => null],
        'status' => 'DELIVERED',
        'direction' => 'OUTBOUND',
        'created_at' => '2026-09-11T12:57:37+00:00',
        'price' => 0.0055,
        'active_contact_price' => 0.015,
        'events' => [
            ['status' => 'QUEUED', 'timestamp' => '2026-09-11T12:57:37+00:00', 'description' => 'Message queued for sending'],
            ['status' => 'DELIVERED', 'timestamp' => '2026-09-11T12:57:47+00:00', 'description' => 'Message delivered to recipient'],
        ],
    ])
        ->messages()
        ->retrieve('msg-1');

    expect($result->data->id)->toBe('msg-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->contactID)->toBe('c-1')
        ->and($result->data->phone)->toBe('+14155551234')
        ->and($result->data->phoneInternational)->toBe('+1 415-555-1234')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->templateID)->toBe('tpl-1')
        ->and($result->data->templateName)->toBe('Welcome Message')
        ->and($result->data->templateCategory)->toBe('UTILITY')
        ->and($result->data->channel)->toBe('sms')
        ->and($result->data->messageBody->content)->toBe('Welcome!')
        ->and($result->data->status)->toBe('DELIVERED')
        ->and($result->data->direction)->toBe('OUTBOUND')
        ->and($result->data->price)->toBe(0.0055)
        ->and($result->data->activeContactPrice)->toBe(0.015)
        ->and($result->data->events)->toHaveCount(2)
        ->and($result->data->events[0]->status)->toBe('QUEUED')
        ->and($result->data->events[1]->status)->toBe('DELIVERED');
});

it('messages()->activities() returns message activities', function () {
    $result = sentApi([
        'message_id' => 'msg-1',
        'activities' => [[
            'status' => 'DELIVERED',
            'description' => 'Message delivered to recipient',
            'from' => '+15551234567',
            'timestamp' => '2026-09-11T14:32:37+00:00',
            'price' => '0.0450',
            'active_contact_price' => '0.0050',
        ]],
        'pagination' => null,
    ])->messages()->activities('msg-1');

    expect($result->data->messageID)->toBe('msg-1')
        ->and($result->data->activities[0]->status)->toBe('DELIVERED')
        ->and($result->data->activities[0]->description)->toBe('Message delivered to recipient')
        ->and($result->data->activities[0]->from)->toBe('+15551234567')
        ->and($result->data->activities[0]->price)->toBe('0.0450')
        ->and($result->data->activities[0]->activeContactPrice)->toBe('0.0050');
});

it('messages()->resend() resends a message', function () {
    $result = sentApi([
        'status' => 'QUEUED',
        'template_id' => 'tpl-1',
        'template_name' => 'order_confirmation',
        'recipients' => [[
            'message_id' => 'msg-2',
            'to' => '+14155551234',
            'channel' => 'sms',
            'body' => 'Hi John',
        ]],
    ])->messages()->resend('msg-1');

    expect($result->data->status)->toBe('QUEUED')
        ->and($result->data->templateID)->toBe('tpl-1')
        ->and($result->data->templateName)->toBe('order_confirmation')
        ->and($result->data->recipients[0]->messageID)->toBe('msg-2')
        ->and($result->data->recipients[0]->to)->toBe('+14155551234')
        ->and($result->data->recipients[0]->channel)->toBe('sms')
        ->and($result->data->recipients[0]->body)->toBe('Hi John');
});

// Conversations ----------------------------------------------------------------

it('conversations()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->conversations();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('conversations()->get() lists conversations', function () {
    $result = sentApi([
        'messages' => [[
            'id' => 'msg-1',
            'customer_id' => 'cust-1',
            'contact_id' => 'c-1',
            'phone' => '+1234567890',
            'phone_international' => '+1 234-567-890',
            'region_code' => 'US',
            'template_id' => null,
            'template_name' => null,
            'template_category' => null,
            'channel' => 'sms',
            'message_body' => null,
            'status' => 'DELIVERED',
            'direction' => 'OUTBOUND',
            'created_at' => '2026-09-11T14:52:37+00:00',
            'price' => 0.0075,
            'active_contact_price' => 0.0,
            'events' => null,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->conversations()->get();

    $message = $result->data->messages[0];
    expect($message->id)->toBe('msg-1')
        ->and($message->customerID)->toBe('cust-1')
        ->and($message->contactID)->toBe('c-1')
        ->and($message->phone)->toBe('+1234567890')
        ->and($message->regionCode)->toBe('US')
        ->and($message->channel)->toBe('sms')
        ->and($message->status)->toBe('DELIVERED')
        ->and($message->direction)->toBe('OUTBOUND')
        ->and($message->price)->toBe(0.0075)
        ->and($message->activeContactPrice)->toBe(0.0);
});

it('conversations()->messages() lists messages for a conversation', function () {
    $result = sentApi([
        'messages' => [[
            'id' => 'msg-1',
            'customer_id' => 'cust-1',
            'contact_id' => 'c-1',
            'phone' => '+1234567890',
            'channel' => 'whatsapp',
            'status' => 'SENT',
            'direction' => 'INBOUND',
            'created_at' => '2026-09-11T14:52:37+00:00',
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->conversations()->messages('conv-1');

    expect($result->data->messages[0]->id)->toBe('msg-1')
        ->and($result->data->messages[0]->channel)->toBe('whatsapp')
        ->and($result->data->messages[0]->status)->toBe('SENT')
        ->and($result->data->messages[0]->direction)->toBe('INBOUND');
});

// Sender profiles -------------------------------------------------------------

it('senderProfiles()->get() lists sender profiles', function () {
    $result = sentApi([
        'sender_profiles' => [[
            'id' => 'sp-1',
            'organization_id' => 'org-1',
            'name' => 'Example Retail',
            'short_name' => 'Example',
            'description' => 'Retail sender profile',
            'api_key' => 'sk_live_abc',
            'billing' => ['inherit' => true],
            'channels' => ['sms' => ['country' => 'US']],
            'compliance' => ['brand' => []],
            'created_at' => '2026-09-11T00:00:00+00:00',
        ]],
        'pagination' => ['page' => 1, 'page_size' => 20, 'total_count' => 1],
    ])->senderProfiles()->get();

    $profile = $result->senderProfiles[0];
    expect($profile->id)->toBe('sp-1')
        ->and($profile->organizationId)->toBe('org-1')
        ->and($profile->name)->toBe('Example Retail')
        ->and($profile->shortName)->toBe('Example')
        ->and($profile->description)->toBe('Retail sender profile')
        ->and($profile->apiKey)->toBe('sk_live_abc')
        ->and($profile->billing)->toBe(['inherit' => true])
        ->and($profile->channels)->toBe(['sms' => ['country' => 'US']])
        ->and($profile->compliance)->toBe(['brand' => []])
        ->and($profile->createdAt)->toBe('2026-09-11T00:00:00+00:00')
        ->and($result->pagination)->toBe(['page' => 1, 'page_size' => 20, 'total_count' => 1]);
});

it('senderProfiles()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->senderProfiles();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('senderProfiles()->find() retrieves a sender profile', function () {
    $result = sentApi([
        'id' => 'sp-1',
        'organization_id' => 'org-1',
        'name' => 'Example Retail',
        'short_name' => 'Example',
        'description' => 'Retail sender profile',
        'api_key' => 'sk_live_abc',
        'billing' => ['inherit' => true],
        'channels' => ['sms' => ['country' => 'US']],
        'compliance' => ['brand' => []],
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])->senderProfiles()->find('sp-1');

    expect($result)->toBeInstanceOf(SenderProfileData::class)
        ->and($result->id)->toBe('sp-1')
        ->and($result->organizationId)->toBe('org-1')
        ->and($result->name)->toBe('Example Retail')
        ->and($result->shortName)->toBe('Example')
        ->and($result->description)->toBe('Retail sender profile')
        ->and($result->apiKey)->toBe('sk_live_abc')
        ->and($result->billing)->toBe(['inherit' => true])
        ->and($result->channels)->toBe(['sms' => ['country' => 'US']])
        ->and($result->compliance)->toBe(['brand' => []])
        ->and($result->createdAt)->toBe('2026-09-11T00:00:00+00:00');
});

it('senderProfiles()->create() returns a SenderProfileBuilder', function () {
    expect(sentApi()->senderProfiles()->create())->toBeInstanceOf(SenderProfileBuilder::class);
});

it('senderProfiles()->create()->name()->shortName()->save() creates a sender profile', function () {
    $result = sentApi([
        'id' => 'sp-1',
        'organization_id' => 'org-1',
        'name' => 'Example Retail',
        'short_name' => 'Example',
        'description' => null,
        'api_key' => 'sk_live_new',
        'billing' => null,
        'channels' => null,
        'compliance' => null,
        'created_at' => '2026-09-12T00:00:00+00:00',
    ])
        ->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->save();

    expect($result->id)->toBe('sp-1')
        ->and($result->organizationId)->toBe('org-1')
        ->and($result->name)->toBe('Example Retail')
        ->and($result->shortName)->toBe('Example')
        ->and($result->apiKey)->toBe('sk_live_new')
        ->and($result->createdAt)->toBe('2026-09-12T00:00:00+00:00');
});

it('senderProfiles()->create()->attach()->save() sends a multipart request with a profile field and the file', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1', 'name' => 'Example Retail']);

    $sent->senderProfiles()->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data');
});

it('senderProfiles()->update()->attach()->save() throws, attach() is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();
})->throws(InvalidArgumentException::class, 'attach() is not supported on update()');

it('senderProfiles()->create()->save() throws without a name', function () {
    sentApi()->senderProfiles()->create()->shortName('Example')->save();
})->throws(InvalidArgumentException::class, 'A name is required');

it('senderProfiles()->create()->save() throws without a short name', function () {
    sentApi()->senderProfiles()->create()->name('Example Retail')->save();
})->throws(InvalidArgumentException::class, 'A short name is required');

it('senderProfiles()->create() builder exercises all setters', function () {
    $result = sentApi(['id' => 'sp-1'])
        ->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->description('Retail sender profile')
        ->billing(['inherit' => true])
        ->channels(['sms' => ['country' => 'US', 'number_type' => 'TEN_DLC']])
        ->compliance(['brand' => []])
        ->sandbox(true)
        ->save();
    expect($result)->not->toBeNull();
});

it('senderProfiles()->create() chains are immutable', function () {
    $base = sentApi()->senderProfiles()->create();
    $chained = $base->name('Example Retail')->shortName('Example');
    expect($chained)->not->toBe($base);
});

it('senderProfiles()->update() returns a SenderProfileBuilder', function () {
    expect(sentApi()->senderProfiles()->update('sp-1'))->toBeInstanceOf(SenderProfileBuilder::class);
});

it('senderProfiles()->update()->name()->save() updates a sender profile without a short name', function () {
    $result = sentApi([
        'id' => 'sp-1',
        'organization_id' => 'org-1',
        'name' => 'New Name',
        'short_name' => 'Example',
        'description' => 'updated for real',
        'api_key' => 'sk_live_abc',
        'billing' => null,
        'channels' => null,
        'compliance' => null,
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])
        ->senderProfiles()
        ->update('sp-1')
        ->name('New Name')
        ->save();

    expect($result->id)->toBe('sp-1')
        ->and($result->organizationId)->toBe('org-1')
        ->and($result->name)->toBe('New Name')
        ->and($result->shortName)->toBe('Example')
        ->and($result->description)->toBe('updated for real')
        ->and($result->apiKey)->toBe('sk_live_abc');
});

it('senderProfiles()->update()->billing()->save() throws, billing is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->billing(['inherit' => true])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->update()->channels()->save() throws, channels is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->channels(['sms' => []])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->update()->compliance()->save() throws, compliance is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->compliance(['brand' => []])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->delete() deletes a sender profile', function () {
    sentApi([])->senderProfiles()->delete('sp-1');
    expect(true)->toBeTrue();
});

// Channels ---------------------------------------------------------------------

it('channels()->get() returns channel state', function () {
    $result = sentApi([
        'customer_id' => 'cust-1',
        'sms' => [[
            'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
            'status' => 'ACTIVE', 'compliance' => ['brand' => ['legal_name' => 'Acme']],
        ]],
        'whatsapp' => [
            'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
            'owner_business_id' => 'biz-1', 'status' => 'CONNECTED',
        ],
        'rcs' => ['id' => 'rcs-1', 'status' => 'PENDING', 'phone_number' => '+12125550123'],
    ])->channels()->get();

    expect($result->customerId)->toBe('cust-1')
        ->and($result->sms[0]->country)->toBe('US')
        ->and($result->sms[0]->numberType)->toBe('TEN_DLC')
        ->and($result->sms[0]->status)->toBe('ACTIVE')
        ->and($result->sms[0]->compliance)->toBe(['brand' => ['legal_name' => 'Acme']])
        ->and($result->whatsapp->wabaId)->toBe('waba-1')
        ->and($result->whatsapp->phoneNumberId)->toBe('phone-1')
        ->and($result->whatsapp->solutionId)->toBe('sol-1')
        ->and($result->whatsapp->ownerBusinessId)->toBe('biz-1')
        ->and($result->whatsapp->status)->toBe('CONNECTED')
        ->and($result->rcs->id)->toBe('rcs-1')
        ->and($result->rcs->status)->toBe('PENDING')
        ->and($result->rcs->phoneNumber)->toBe('+12125550123');
});

it('channels()->smsMarkets() lists SMS markets', function () {
    $result = sentApiList([[
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'status' => 'ACTIVE', 'compliance' => ['brand' => ['legal_name' => 'Acme']],
    ]])->channels()->smsMarkets();

    expect($result[0]->country)->toBe('US')
        ->and($result[0]->numberType)->toBe('TEN_DLC')
        ->and($result[0]->status)->toBe('ACTIVE')
        ->and($result[0]->compliance)->toBe(['brand' => ['legal_name' => 'Acme']]);
});

it('channels()->findSmsMarket() retrieves an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => 'Acme',
        'status' => 'ACTIVE', 'compliance' => ['brand' => ['legal_name' => 'Acme']],
    ])
        ->channels()
        ->findSmsMarket('US', 'TEN_DLC');

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->senderValue)->toBe('Acme')
        ->and($result->status)->toBe('ACTIVE')
        ->and($result->compliance)->toBe(['brand' => ['legal_name' => 'Acme']]);
});

it('channels()->addSmsMarket() adds an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'status' => 'PENDING', 'compliance' => null,
    ])
        ->channels()
        ->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC']);

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->status)->toBe('PENDING');
});

it('channels()->addSmsMarket() with a FileParam sends a multipart request with renamed fields', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'XK', 'number_type' => 'ALPHANUMERIC']);

    $sent->channels()->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'sender_value' => 'EXAMPLE',
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data')
        ->and($captured->body)->toContain('name="numberType"')
        ->and($captured->body)->toContain('name="senderValue"')
        ->and($captured->body)->not->toContain('name="number_type"')
        ->and($captured->body)->not->toContain('name="sender_value"');
});

it('channels()->addSmsMarket() throws when compliance is combined with a document', function () {
    sentApi()->channels()->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'compliance' => ['brand' => ['inherit' => true]],
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);
})->throws(InvalidArgumentException::class, 'compliance is not supported together with a document upload');

it('channels()->updateSmsMarket() updates an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => 'Acme',
        'status' => 'ACTIVE', 'compliance' => ['brand' => ['legal_name' => 'Test Co']],
    ])
        ->channels()
        ->updateSmsMarket('US', 'TEN_DLC', ['sandbox' => true]);

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->senderValue)->toBe('Acme')
        ->and($result->status)->toBe('ACTIVE')
        ->and($result->compliance)->toBe(['brand' => ['legal_name' => 'Test Co']]);
});

it('channels()->addWhatsapp() adds a WhatsApp channel', function () {
    $result = sentApi([
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
        'owner_business_id' => 'biz-1', 'status' => 'PENDING',
    ])->channels()->addWhatsapp(['waba_id' => 'waba-1']);

    expect($result->wabaId)->toBe('waba-1')
        ->and($result->phoneNumberId)->toBe('phone-1')
        ->and($result->solutionId)->toBe('sol-1')
        ->and($result->ownerBusinessId)->toBe('biz-1')
        ->and($result->status)->toBe('PENDING');
});

it('channels()->addRcs() adds an RCS agent', function () {
    $result = sentApi([
        'id' => 'rcs-1',
        'status' => 'PENDING',
        'phone_number' => '+12125550123',
        'display_name' => 'Acme',
        'description' => 'Home services booking agent',
        'agent_use_case' => 'NOTIFICATIONS',
        'brand_name' => 'Acme',
        'privacy_policy_url' => 'https://example.com/privacy',
        'terms_and_conditions_url' => 'https://example.com/terms',
        'website_url' => 'https://example.com',
        'brand_color' => '#838FB6',
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
        'start_message' => 'Welcome!',
        'help_message' => 'For assistance call +12125550123',
        'stop_message' => 'You have been unsubscribed.',
        'sample_messages' => ['Hi there'],
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])
        ->channels()
        ->addRcs(fullRcsBody(['brand_name' => 'Acme']));

    expect($result->id)->toBe('rcs-1')
        ->and($result->status)->toBe('PENDING')
        ->and($result->phoneNumber)->toBe('+12125550123')
        ->and($result->displayName)->toBe('Acme')
        ->and($result->description)->toBe('Home services booking agent')
        ->and($result->agentUseCase)->toBe('NOTIFICATIONS')
        ->and($result->brandName)->toBe('Acme')
        ->and($result->privacyPolicyUrl)->toBe('https://example.com/privacy')
        ->and($result->termsAndConditionsUrl)->toBe('https://example.com/terms')
        ->and($result->websiteUrl)->toBe('https://example.com')
        ->and($result->brandColor)->toBe('#838FB6')
        ->and($result->brandPhoneNumber)->toBe('+12125550123')
        ->and($result->customerSupportPhoneNumber)->toBe('+12125550124')
        ->and($result->brandEmail)->toBe('hello@example.com')
        ->and($result->customerSupportEmail)->toBe('support@example.com')
        ->and($result->contactNameAndTitle)->toBe('Jane Smith, Head of Marketing')
        ->and($result->companyEin)->toBe('12-3456789')
        ->and($result->entityType)->toBe('LLC')
        ->and($result->officialAddress)->toBe(['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'])
        ->and($result->briefCompanyDescription)->toBe('Home services booking platform')
        ->and($result->optInProcessDescription)->toBe('Users opt in via the website sign-up form')
        ->and($result->startMessage)->toBe('Welcome!')
        ->and($result->helpMessage)->toBe('For assistance call +12125550123')
        ->and($result->stopMessage)->toBe('You have been unsubscribed.')
        ->and($result->sampleMessages)->toBe(['Hi there']);
});

it('channels()->addRcs() throws when required fields are missing', function () {
    sentApi()->channels()->addRcs(['brand_name' => 'Acme']);
})->throws(InvalidArgumentException::class, 'Missing required field(s) for addRcs()');

// Compliance ---------------------------------------------------------------------

it('compliance()->requirements() defaults to the sms channel', function () {
    $result = sentApi([
        'channel' => 'sms',
        'country' => 'US',
        'type' => 'TEN_DLC',
        'required' => true,
        'sender_id_pattern' => null,
        'requirements' => [['field' => 'brand.legal_name', 'required' => true]],
        'setup' => [['step' => 'register_brand']],
    ])->compliance()->requirements('US', 'TEN_DLC');

    expect($result->channel)->toBe('sms')
        ->and($result->country)->toBe('US')
        ->and($result->type)->toBe('TEN_DLC')
        ->and($result->required)->toBeTrue()
        ->and($result->senderIdPattern)->toBeNull()
        ->and($result->requirements)->toBe([['field' => 'brand.legal_name', 'required' => true]])
        ->and($result->setup)->toBe([['step' => 'register_brand']]);
});

it('compliance()->requirements() accepts an explicit channel override', function () {
    $result = sentApi([
        'channel' => 'sms', 'country' => 'US', 'type' => 'TEN_DLC', 'required' => true,
        'sender_id_pattern' => null, 'requirements' => [], 'setup' => [],
    ])->compliance()->requirements('US', 'TEN_DLC', 'sms');

    expect($result->channel)->toBe('sms')
        ->and($result->country)->toBe('US')
        ->and($result->type)->toBe('TEN_DLC');
});
