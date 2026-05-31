<?php

declare(strict_types=1);

use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

/**
 * @param  array<string, mixed>  $data
 */
function webhookPayload(string $subType, array $data = []): WebhookPayload
{
    return WebhookPayload::fromArray([
        'field' => 'message',
        'sub_type' => $subType,
        'timestamp' => '2025-10-31T10:10:42Z',
        'payload' => array_merge([
            'message_id' => 'msg_1',
            'message_status' => 'DELIVERED',
            'channel' => 'sms',
            'inbound_number' => '+61412345678',
            'outbound_number' => '+61498765432',
            'template_id' => 'tpl_1',
        ], $data),
    ]);
}

it('parses a webhook payload', function () {
    $payload = webhookPayload('message.delivered');

    expect($payload->field)->toBe('message')
        ->and($payload->subType)->toBe('message.delivered')
        ->and($payload->messageId())->toBe('msg_1')
        ->and($payload->status())->toBe('DELIVERED')
        ->and($payload->channel())->toBe('sms')
        ->and($payload->recipient())->toBe('+61412345678')
        ->and($payload->sender())->toBe('+61498765432')
        ->and($payload->templateId())->toBe('tpl_1')
        ->and($payload->dedupKey())->toBe('msg_1.message.delivered');
});

it('returns null dedup key when message id is absent', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'sub_type' => 'message.received',
        'payload' => ['from' => '+61412345678'],
    ]);

    expect($payload->dedupKey())->toBeNull()
        ->and($payload->messageId())->toBeNull();
});

it('MessageSent holds message and response in job context', function () {
    $message = SentMessage::create()->to('+61412345678');
    $event = new MessageSent($message, ['id' => 'msg_123']);

    expect($event->message)->toBe($message)
        ->and($event->response)->toBe(['id' => 'msg_123'])
        ->and($event->payload)->toBeNull();
});

it('MessageSent can be built from a webhook payload', function () {
    $payload = webhookPayload('message.sent');
    $event = MessageSent::fromWebhook($payload);

    expect($event->message)->toBeNull()
        ->and($event->response)->toBeNull()
        ->and($event->payload)->toBe($payload);
});

it('MessageFailed holds message and exception in job context', function () {
    $message = SentMessage::create()->to('+61412345678');
    $exception = new RuntimeException('API error');
    $event = new MessageFailed($message, $exception);

    expect($event->message)->toBe($message)
        ->and($event->exception)->toBe($exception)
        ->and($event->payload)->toBeNull();
});

it('MessageFailed can be built from a webhook payload', function () {
    $payload = webhookPayload('message.failed');
    $event = MessageFailed::fromWebhook($payload);

    expect($event->message)->toBeNull()
        ->and($event->exception)->toBeNull()
        ->and($event->payload)->toBe($payload);
});

it('webhook-only events carry the payload', function () {
    $payload = webhookPayload('message.delivered');

    expect((new MessageQueued($payload))->payload)->toBe($payload)
        ->and((new MessageRouted($payload))->payload)->toBe($payload)
        ->and((new MessageDelivered($payload))->payload)->toBe($payload)
        ->and((new MessageRead($payload))->payload)->toBe($payload)
        ->and((new MessageReceived($payload))->payload)->toBe($payload);
});

it('WebhookPayload returns accountId from payload', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'sub_type' => 'message.delivered',
        'payload' => ['account_id' => 'acc_xyz', 'message_id' => 'msg_1'],
    ]);

    expect($payload->accountId())->toBe('acc_xyz');
});

it('WebhookPayload string() returns null when field value is non-string', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'sub_type' => 'message.delivered',
        'payload' => ['message_id' => 12345, 'channel' => null],
    ]);

    expect($payload->messageId())->toBeNull()
        ->and($payload->channel())->toBeNull();
});
