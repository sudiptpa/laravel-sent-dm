<?php

declare(strict_types=1);

use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Listeners\SyncMessageStatus;
use Sujip\SentDm\Models\SentLog;
use Sujip\SentDm\Webhooks\WebhookPayload;

function makeWebhookPayload(string $subType, string $messageId): WebhookPayload
{
    return WebhookPayload::fromArray([
        'field' => 'message',
        'sub_type' => $subType,
        'payload' => ['message_id' => $messageId],
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

it('updates status to failed on MessageFailed webhook', function () {
    (new SyncMessageStatus)->handle(MessageFailed::fromWebhook(makeWebhookPayload('message.failed', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Failed);
});

it('updates status to read on MessageRead', function () {
    (new SyncMessageStatus)->handle(new MessageRead(makeWebhookPayload('message.read', 'msg-001')));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Read);
});

it('skips job context MessageSent (payload is null)', function () {
    $event = new MessageSent(message: null, response: null, payload: null);

    (new SyncMessageStatus)->handle($event);

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Queued);
});

it('skips when message_id is absent in payload', function () {
    $payload = WebhookPayload::fromArray(['field' => 'message', 'sub_type' => 'message.delivered', 'payload' => []]);

    (new SyncMessageStatus)->handle(new MessageDelivered($payload));

    expect(SentLog::where('message_id', 'msg-001')->first()?->status)->toBe(SentLogStatus::Queued);
});
