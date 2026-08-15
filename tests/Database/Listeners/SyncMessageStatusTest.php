<?php

declare(strict_types=1);

use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Listeners\SyncMessageStatus;
use Sujip\SentDm\Models\SentLog;
use Sujip\SentDm\Webhooks\WebhookPayload;

function makeWebhookPayload(string $subType, string $messageId): WebhookPayload
{
    return WebhookPayload::fromArray([
        'field' => 'message',
        'event' => $subType,
        'payload' => [
            'message_id' => $messageId,
            'inbound_number' => '+61412345678',
            'channel' => 'sms',
        ],
    ]);
}

beforeEach(function () {
    SentLog::create(['recipient' => '+61412345678', 'message_id' => 'msg-001', 'status' => SentLogStatus::Queued]);
});

it('updates status to sent on MessageSent webhook', function () {
    (new SyncMessageStatus)->handle(MessageSent::fromWebhook(makeWebhookPayload('message.sent', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Sent);
});

it('updates status to delivered on MessageDelivered', function () {
    (new SyncMessageStatus)->handle(new MessageDelivered(makeWebhookPayload('message.delivered', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Delivered);
});

it('creates a placeholder row when log does not exist yet (race: webhook before job)', function () {
    (new SyncMessageStatus)->handle(new MessageDelivered(makeWebhookPayload('message.delivered', 'msg-new')));

    $log = SentLog::where('message_id', 'msg-new')->first();
    expect($log)->not->toBeNull()
        ->and($log?->status)->toBe(SentLogStatus::Delivered)
        ->and($log?->recipient)->toBe('+61412345678'); // from the webhook payload fixture
});

it('updates status to failed on MessageFailed webhook', function () {
    (new SyncMessageStatus)->handle(MessageFailed::fromWebhook(makeWebhookPayload('message.failed', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Failed);
});

it('updates status to read on MessageRead', function () {
    (new SyncMessageStatus)->handle(new MessageRead(makeWebhookPayload('message.read', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Read);
});

it('updates status to filtered on MessageFiltered', function () {
    (new SyncMessageStatus)->handle(new MessageFiltered(makeWebhookPayload('message.filtered', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Filtered);
});

it('updates status to blocked on MessageBlocked', function () {
    (new SyncMessageStatus)->handle(new MessageBlocked(makeWebhookPayload('message.blocked', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Blocked);
});

it('updates status to scheduled on MessageScheduled', function () {
    (new SyncMessageStatus)->handle(new MessageScheduled(makeWebhookPayload('message.scheduled', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Scheduled);
});

it('skips job context MessageSent (payload is null)', function () {
    $event = new MessageSent(message: null, response: null, payload: null);

    (new SyncMessageStatus)->handle($event);

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Queued);
});

it('skips when message_id is absent in payload', function () {
    $payload = WebhookPayload::fromArray(['field' => 'message', 'event' => 'message.delivered', 'payload' => []]);

    (new SyncMessageStatus)->handle(new MessageDelivered($payload));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Queued);
});
