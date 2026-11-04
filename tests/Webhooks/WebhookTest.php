<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Sujip\SentDm\Events\CallAgentConnected;
use Sujip\SentDm\Events\CallAnswered;
use Sujip\SentDm\Events\CallCompleted;
use Sujip\SentDm\Events\CallFailed;
use Sujip\SentDm\Events\CallInitiated;
use Sujip\SentDm\Events\CallRecordingReady;
use Sujip\SentDm\Events\ChannelActivated;
use Sujip\SentDm\Events\ChannelApproved;
use Sujip\SentDm\Events\ChannelChangesRequested;
use Sujip\SentDm\Events\ChannelDeactivated;
use Sujip\SentDm\Events\ChannelRejected;
use Sujip\SentDm\Events\ChannelRemoved;
use Sujip\SentDm\Events\ChannelSubmitted;
use Sujip\SentDm\Events\ContactCustomKeyword;
use Sujip\SentDm\Events\ContactHelpRequested;
use Sujip\SentDm\Events\ContactOptedIn;
use Sujip\SentDm\Events\ContactOptedOut;
use Sujip\SentDm\Events\LinkClicked;
use Sujip\SentDm\Events\LinkDownloaded;
use Sujip\SentDm\Events\LinkExpired;
use Sujip\SentDm\Events\LinkRevoked;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageCancelled;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Events\TemplateApproved;
use Sujip\SentDm\Events\TemplateCategoryUpdated;
use Sujip\SentDm\Events\TemplateDeleted;
use Sujip\SentDm\Events\TemplateDisabled;
use Sujip\SentDm\Events\TemplatePaused;
use Sujip\SentDm\Events\TemplatePending;
use Sujip\SentDm\Events\TemplateQualityUpdated;
use Sujip\SentDm\Events\TemplateRejected;
use Sujip\SentDm\Events\UnknownWebhookEvent;
use Sujip\SentDm\Webhooks\SentWebhookController;

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

it('accepts a valid v2 signed request', function () {
    Event::fake();
    $payload = msgPayload('message.delivered');
    [$raw, $headers] = signedWebhook($payload);
    $headers = [
        'webhook-signature' => $headers['x-webhook-signature'],
        'webhook-id' => $headers['x-webhook-id'],
        'webhook-timestamp' => $headers['x-webhook-timestamp'],
        'Content-Type' => 'application/json',
    ];

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

it('dispatches MessageCancelled for message.cancelled', function () {
    Event::fake();
    $payload = msgPayload('message.cancelled', [
        'message_status' => 'CANCELLED',
        'scheduled_at' => '2026-10-01T07:00:00+00:00',
    ]);
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw);

    Event::assertDispatched(MessageCancelled::class, fn (MessageCancelled $e) => $e->payload->status() === 'CANCELLED'
        && $e->payload->scheduledAt() === '2026-10-01T07:00:00+00:00'
    );
});

it('logs a warning for an unrecognized event type', function () {
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

it('dispatches unknown webhook events', function () {
    Event::fake();
    $payload = msgPayload('message.unknown');
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);

    Event::assertNotDispatched(MessageDelivered::class);
    Event::assertNotDispatched(MessageSent::class);
    Event::assertDispatched(UnknownWebhookEvent::class, fn (UnknownWebhookEvent $event) => $event->payload->subType === 'message.unknown');
});

it('uses the configured webhook dedup ttl', function () {
    config()->set('sent.webhook.dedup_ttl', 120);

    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('add')
        ->once()
        ->with('sent.webhook.event.msg_1.message.delivered', true, 120)
        ->andReturn(true);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('warning');

    $request = request()->create('/sent/webhook', 'POST', [], [], [], [], json_encode(msgPayload('message.delivered')) ?: '');

    (new SentWebhookController($cache, $logger))($request);
});

it('falls back when webhook dedup ttl is invalid', function () {
    config()->set('sent.webhook.dedup_ttl', 'bad');

    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('add')
        ->once()
        ->with('sent.webhook.event.msg_1.message.delivered', true, 86400)
        ->andReturn(true);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('warning');

    $request = request()->create('/sent/webhook', 'POST', [], [], [], [], json_encode(msgPayload('message.delivered')) ?: '');

    (new SentWebhookController($cache, $logger))($request);
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

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function callPayload(string $subType, array $extra = []): array
{
    return [
        'field' => 'call',
        'event' => $subType,
        'timestamp' => '2026-11-04T07:46:29Z',
        'payload' => array_merge([
            'account_id' => 'acc_1',
            'call_id' => 'call_1',
            'channel' => 'voice',
            'number' => '+61498765432',
            'mode' => 'agent',
            'agent_id' => 'agent_1',
            'updated_at' => '2026-11-04T07:46:29Z',
        ], $extra),
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $extraHeaders
 */
function postWebhook(array $payload, array $extraHeaders = []): void
{
    [$raw, $headers] = signedWebhook($payload);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars([...$headers, ...$extraHeaders]), $raw)->assertStatus(200);
}

it('dispatches the matching typed event for every non-message webhook', function (string $subType, string $event) {
    Event::fake();
    $field = explode('.', $subType)[0];

    postWebhook([
        'field' => $field,
        'event' => $subType,
        'timestamp' => '2026-11-04T07:46:29Z',
        'payload' => ['account_id' => 'acc_1', 'call_id' => 'call_1', 'contact_id' => 'contact_1'],
    ]);

    Event::assertDispatched($event, fn ($e) => $e->payload->subType === $subType);
    Event::assertNotDispatched(UnknownWebhookEvent::class);
})->with([
    ['call.initiated', CallInitiated::class],
    ['call.answered', CallAnswered::class],
    ['call.agent_connected', CallAgentConnected::class],
    ['call.completed', CallCompleted::class],
    ['call.failed', CallFailed::class],
    ['call.recording_ready', CallRecordingReady::class],
    ['contact.opt_in', ContactOptedIn::class],
    ['contact.opt_out', ContactOptedOut::class],
    ['contact.help', ContactHelpRequested::class],
    ['contact.custom_keyword', ContactCustomKeyword::class],
    ['templates.approved', TemplateApproved::class],
    ['templates.rejected', TemplateRejected::class],
    ['templates.pending', TemplatePending::class],
    ['templates.deleted', TemplateDeleted::class],
    ['templates.category_updated', TemplateCategoryUpdated::class],
    ['templates.quality_updated', TemplateQualityUpdated::class],
    ['templates.paused', TemplatePaused::class],
    ['templates.disabled', TemplateDisabled::class],
    ['link.clicked', LinkClicked::class],
    ['link.downloaded', LinkDownloaded::class],
    ['link.expired', LinkExpired::class],
    ['link.revoked', LinkRevoked::class],
    ['channel.submitted', ChannelSubmitted::class],
    ['channel.changes_requested', ChannelChangesRequested::class],
    ['channel.approved', ChannelApproved::class],
    ['channel.activated', ChannelActivated::class],
    ['channel.deactivated', ChannelDeactivated::class],
    ['channel.rejected', ChannelRejected::class],
    ['channel.removed', ChannelRemoved::class],

]);

it('still routes an unrecognised channel milestone to UnknownWebhookEvent', function () {
    Event::fake();

    postWebhook(['field' => 'channel', 'event' => 'channel.some_new_milestone', 'timestamp' => '2026-11-04T07:46:29Z', 'payload' => ['country' => 'US']]);

    Event::assertDispatched(UnknownWebhookEvent::class, fn (UnknownWebhookEvent $e) => $e->payload->subType === 'channel.some_new_milestone');
});

it('deduplicates a retried contact event by its delivery id', function () {
    Event::fake();
    $payload = ['field' => 'contact', 'event' => 'contact.opt_out', 'timestamp' => '2026-11-04T07:46:29Z', 'payload' => ['contact_id' => 'c_1', 'opt_out' => true, 'source' => 'keyword']];

    postWebhook($payload, ['webhook-id' => 'evt_1']);
    postWebhook($payload, ['webhook-id' => 'evt_1']);
    postWebhook($payload, ['webhook-id' => 'evt_2']);

    Event::assertDispatchedTimes(ContactOptedOut::class, 2);
});

it('reads the version 1 event id header for deduplication', function () {
    Event::fake();
    $payload = ['field' => 'templates', 'event' => 'templates.approved', 'timestamp' => '2026-11-04T07:46:29Z', 'payload' => ['whatsapp_template_id' => 'wa_1', 'status' => 'APPROVED']];

    postWebhook($payload, ['x-webhook-event-id' => 'evt_9']);
    postWebhook($payload, ['x-webhook-event-id' => 'evt_9']);

    Event::assertDispatchedTimes(TemplateApproved::class, 1);
});

it('does not merge two link clicks that share a message id', function () {
    Event::fake();

    foreach (['2026-11-04T07:46:29Z', '2026-11-04T07:46:35Z'] as $when) {
        postWebhook(['field' => 'link', 'event' => 'link.clicked', 'timestamp' => $when, 'payload' => ['message_id' => 'msg_1', 'record_id' => 'rec_1', 'occurred_at' => $when, 'traffic_class' => 'human']]);
    }

    Event::assertDispatchedTimes(LinkClicked::class, 2);
});

it('deduplicates an identical retried event that has no delivery id', function () {
    Event::fake();
    $payload = ['field' => 'channel', 'event' => 'channel.activated', 'timestamp' => '2026-11-04T07:46:29Z', 'payload' => ['country' => 'US', 'status' => 'active']];

    postWebhook($payload);
    postWebhook($payload);

    Event::assertDispatchedTimes(ChannelActivated::class, 1);
});

it('does not treat different call events for one call as duplicates', function () {
    Event::fake();

    foreach (['call.initiated', 'call.answered', 'call.completed'] as $subType) {
        [$raw, $headers] = signedWebhook(callPayload($subType));
        test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);
    }

    Event::assertDispatchedTimes(CallInitiated::class, 1);
    Event::assertDispatchedTimes(CallAnswered::class, 1);
    Event::assertDispatchedTimes(CallCompleted::class, 1);
});

it('deduplicates a retried call event', function () {
    Event::fake();
    [$raw, $headers] = signedWebhook(callPayload('call.completed', ['duration_seconds' => 42]));

    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);
    test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);

    Event::assertDispatchedTimes(CallCompleted::class, 1);
});

it('delivers each recording of one call separately', function () {
    Event::fake();

    foreach (['rec_1', 'rec_2'] as $recordingId) {
        [$raw, $headers] = signedWebhook(callPayload('call.recording_ready', ['recording_id' => $recordingId]));
        test()->call('POST', '/sent/webhook', [], [], [], serverVars($headers), $raw)->assertStatus(200);
    }

    Event::assertDispatchedTimes(CallRecordingReady::class, 2);
});
