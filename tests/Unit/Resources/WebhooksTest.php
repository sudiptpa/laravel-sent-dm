<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\Core\Exceptions\NotFoundException;
use SentDm\RequestOptions;
use SentDm\Webhooks\ChannelEvent;
use SentDm\Webhooks\ContactEvent;
use Sujip\SentDm\Builders\WebhookBuilder;
use Sujip\SentDm\Sent;

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

it('webhooks()->create()->senderProfile()->save() passes sender profile clone settings', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'wh-1']);

    $sent->webhooks()
        ->create()
        ->name('My webhook')
        ->url('https://example.com/wh')
        ->events(['message'])
        ->senderProfile(['message'], ['message' => ['delivered']])
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['sender_profile'])->toBe([
        'event_types' => ['message'],
        'event_filters' => ['message' => ['delivered']],
    ]);
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
                        'from' => '+61412345678',
                        'to' => '+61498765432',
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
        ->and($contact->payload->from)->toBe('+61412345678')
        ->and($contact->payload->to)->toBe('+61498765432')
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
