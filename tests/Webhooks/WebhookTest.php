<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;

const TEST_WEBHOOK_SECRET = 'whsec_dGVzdC13ZWJob29rLXNlY3JldA==';

/**
 * Build signed headers + raw body for a webhook request.
 *
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: array<string, string>}
 */
function signedWebhook(array $payload, ?int $timestamp = null, string $secret = TEST_WEBHOOK_SECRET): array
{
    $rawBody = json_encode($payload) ?: '';
    $webhookId = 'wh_test';
    $ts = $timestamp ?? time();
    $key = base64_decode(substr($secret, 6));
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', $webhookId.'.'.$ts.'.'.$rawBody, $key, true));

    return [$rawBody, [
        'x-webhook-signature' => $signature,
        'x-webhook-id' => $webhookId,
        'x-webhook-timestamp' => (string) $ts,
        'Content-Type' => 'application/json',
    ]];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function msgPayload(string $subType, array $extra = []): array
{
    return [
        'field' => 'message',
        'event' => $subType,
        'timestamp' => '2025-10-31T10:10:42Z',
        'payload' => array_merge([
            'account_id' => 'acc_1',
            'message_id' => 'msg_1',
            'message_status' => strtoupper(explode('.', $subType)[1] ?? ''),
            'channel' => 'sms',
            'inbound_number' => '+61412345678',
            'outbound_number' => '+61498765432',
            'template_id' => 'tpl_1',
        ], $extra),
    ];
}

/** @param array<string, string> $headers */
function serverVars(array $headers): array
{
    $server = [];
    foreach ($headers as $k => $v) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
    }

    return $server;
}

it('accepts a valid signed request with a raw (non-whsec_) secret', function () {
    Event::fake();
    $rawSecret = 'raw-secret-no-prefix';
    config()->set('sent.webhook.secret', $rawSecret);

    $payload = msgPayload('message.delivered');
    $rawBody = json_encode($payload) ?: '';
    $webhookId = 'wh_test';
    $ts = time();
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', $webhookId.'.'.$ts.'.'.$rawBody, $rawSecret, true));
    $headers = [
        'x-webhook-signature' => $signature,
        'x-webhook-id' => $webhookId,
        'x-webhook-timestamp' => (string) $ts,
        'Content-Type' => 'application/json',
    ];

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $rawBody)->assertStatus(200);
});

it('returns 500 when webhook secret is not configured', function () {
    config()->set('sent.webhook.secret', null);
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(500);
});

it('returns 500 when webhook secret has invalid base64 after whsec_ prefix', function () {
    config()->set('sent.webhook.secret', 'whsec_!!!not-valid-base64!!!');
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(500);
});

it('returns 401 when signature headers are missing', function () {
    $payload = msgPayload('message.delivered');
    $raw = json_encode($payload) ?: '';

    test()->call('POST', '/sent/webhook', [], [], [], serverVars(['Content-Type' => 'application/json']), $raw)
        ->assertStatus(401);
});

it('returns 401 when signature is invalid', function () {
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);
    $headers['x-webhook-signature'] = 'v1,'.base64_encode('wrong');

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(401);
});

it('returns 401 when timestamp is outside replay window', function () {
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload, time() - 600);

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(401);
});

it('accepts a valid signed request', function () {
    Event::fake();
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)
        ->assertStatus(200)->assertJson(['message' => 'OK']);
});

it('dispatches MessageQueued for message.queued', function () {
    Event::fake();
    $payload = msgPayload('message.queued');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageQueued::class, fn (MessageQueued $e) => $e->payload->messageId() === 'msg_1' && $e->payload->subType === 'message.queued'
    );
});

it('dispatches MessageRouted for message.routed', function () {
    Event::fake();
    $payload = msgPayload('message.routed');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageRouted::class);
});

it('dispatches MessageSent for message.sent', function () {
    Event::fake();
    $payload = msgPayload('message.sent');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $e->message === null && $e->payload?->recipient() === '+61412345678'
    );
});

it('dispatches MessageDelivered for message.delivered', function () {
    Event::fake();
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageDelivered::class, fn (MessageDelivered $e) => $e->payload->status() === 'DELIVERED' && $e->payload->channel() === 'sms'
    );
});

it('dispatches MessageRead for message.read', function () {
    Event::fake();
    $payload = msgPayload('message.read');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageRead::class);
});

it('dispatches MessageFailed for message.failed', function () {
    Event::fake();
    $payload = msgPayload('message.failed');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageFailed::class, fn (MessageFailed $e) => $e->exception === null && $e->payload?->messageId() === 'msg_1'
    );
});

it('dispatches MessageFiltered for message.filtered', function () {
    Event::fake();
    $payload = msgPayload('message.filtered');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageFiltered::class, fn (MessageFiltered $e) => $e->payload->status() === 'FILTERED'
    );
});

it('dispatches MessageBlocked for message.blocked', function () {
    Event::fake();
    $payload = msgPayload('message.blocked');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageBlocked::class, fn (MessageBlocked $e) => $e->payload->status() === 'BLOCKED'
    );
});

it('dispatches MessageScheduled for message.scheduled', function () {
    Event::fake();
    $payload = msgPayload('message.scheduled');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageScheduled::class, fn (MessageScheduled $e) => $e->payload->status() === 'SCHEDULED'
    );
});

it('logs a warning and dispatches nothing for an unrecognized event type', function () {
    Event::fake();
    Log::shouldReceive('warning')
        ->once()
        ->with('sent: unrecognized webhook event type', ['event' => 'message.something_new', 'message_id' => 'msg_1']);

    $payload = msgPayload('message.something_new');
    [$raw, $headers] = signedWebhook($payload);

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)
        ->assertStatus(200)->assertJson(['message' => 'OK']);
});

it('dispatches MessageReceived for inbound message.received', function () {
    Event::fake();
    $payload = [
        'field' => 'message', 'event' => 'message.received', 'timestamp' => '2025-10-31T10:10:42Z',
        'payload' => ['account_id' => 'acc_1', 'from' => '+61412345678', 'to' => '+61498765432', 'text' => 'hello back', 'channel' => 'sms'],
    ];
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageReceived::class, fn (MessageReceived $e) => $e->payload->sender() === '+61412345678' && $e->payload->text() === 'hello back'
    );
});

it('deduplicates events with same message id and sub type', function () {
    Event::fake();
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);

    Event::assertDispatchedTimes(MessageDelivered::class, 1);
});

it('does not dispatch for unknown sub types', function () {
    Event::fake();
    $payload = msgPayload('message.unknown');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);

    Event::assertNotDispatched(MessageDelivered::class);
    Event::assertNotDispatched(MessageSent::class);
});

it('clears dedup cache and re-throws when a listener throws', function () {
    Event::listen(
        MessageDelivered::class,
        fn () => throw new RuntimeException('listener error')
    );

    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);

    test()->withoutExceptionHandling()
        ->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);
})->throws(RuntimeException::class);

it('returns 401 when x-webhook-timestamp is not numeric', function () {
    $payload = msgPayload('message.delivered');
    $raw = json_encode($payload) ?: '';
    $webhookId = 'wh_test';
    $secret = TEST_WEBHOOK_SECRET;
    $key = base64_decode(substr($secret, 6));
    $ts = 'not-a-number';
    $sig = 'v1,'.base64_encode(hash_hmac('sha256', $webhookId.'.'.$ts.'.'.$raw, $key, true));
    $headers = [
        'x-webhook-signature' => $sig,
        'x-webhook-id' => $webhookId,
        'x-webhook-timestamp' => $ts,
        'Content-Type' => 'application/json',
    ];

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)
        ->assertStatus(401);
});
