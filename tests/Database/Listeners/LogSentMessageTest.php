<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SentDm\Messages\MessageSendResponse;
use SentDm\Messages\MessageSendResponse\Data;
use SentDm\Messages\MessageSendResponse\Data\Recipient;
use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Listeners\LogSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Models\SentLog;
use Sujip\SentDm\Webhooks\WebhookPayload;

function makeSendResponse(string $messageId): MessageSendResponse
{
    $recipient = new Recipient;
    $recipient['messageID'] = $messageId;
    $recipient['channel'] = 'sms';

    $data = new Data;
    $data['recipients'] = [$recipient];
    $data['status'] = 'QUEUED';

    $response = new MessageSendResponse;
    $response['data'] = $data;

    return $response;
}

it('creates a SentLog record from job context', function () {
    $message = SentMessage::create()
        ->to('+61412345678')
        ->template('otp')
        ->channel('sms')
        ->idempotencyKey('idem-1');

    $event = new MessageSent(
        message: $message,
        response: makeSendResponse('msg-001'),
        connectionName: 'default',
    );

    (new LogSentMessage)->handle($event);

    $log = SentLog::first();
    expect($log)->not->toBeNull()
        ->and($log->connection)->toBe('default')
        ->and($log->recipient)->toBe('+61412345678')
        ->and($log->channel)->toBe('sms')
        ->and($log->template_name)->toBe('otp')
        ->and($log->message_id)->toBe('msg-001')
        ->and($log->idempotency_key)->toBe('idem-1')
        ->and($log->status)->toBe(SentLogStatus::Queued);
});

it('stores loggable type and id when for() is set on the message', function () {
    $model = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 99;
        }
    };

    $message = SentMessage::create()->to('+61412345678')->template('otp')->for($model);

    (new LogSentMessage)->handle(new MessageSent(
        message: $message,
        response: makeSendResponse('msg-002'),
        connectionName: 'default',
    ));

    $log = SentLog::first();
    expect($log?->loggable_type)->toBe('App\\Models\\User')
        ->and($log?->loggable_id)->toBe('99');
});

it('skips webhook context (message is null)', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'sub_type' => 'message.sent',
        'payload' => ['message_id' => 'msg-003'],
    ]);

    (new LogSentMessage)->handle(MessageSent::fromWebhook($payload));

    expect(SentLog::count())->toBe(0);
});

it('handles response with no recipients gracefully', function () {
    $response = new MessageSendResponse;
    $data = new Data;
    $data['recipients'] = [];
    $response['data'] = $data;

    $message = SentMessage::create()->to('+61412345678')->template('otp');

    (new LogSentMessage)->handle(new MessageSent(message: $message, response: $response, connectionName: 'default'));

    expect(SentLog::first()?->message_id)->toBeNull();
});

it('handles non-MessageSendResponse gracefully', function () {
    $message = SentMessage::create()->to('+61412345678')->template('otp');

    (new LogSentMessage)->handle(new MessageSent(message: $message, response: null, connectionName: 'default'));

    expect(SentLog::first()?->message_id)->toBeNull();
});

it('uses default connection when connectionName is null', function () {
    $message = SentMessage::create()->to('+61412345678')->template('otp');

    (new LogSentMessage)->handle(new MessageSent(message: $message, response: makeSendResponse('msg-004')));

    expect(SentLog::first()?->connection)->toBe('default');
});
