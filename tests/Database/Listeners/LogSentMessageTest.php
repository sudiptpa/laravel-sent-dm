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
        'event' => 'message.sent',
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

it('logs one row per recipient entry when channel() fans out to more than one channel', function () {
    $sms = new Recipient;
    $sms['messageID'] = 'msg-fanout-sms';
    $sms['channel'] = 'sms';
    $sms['to'] = '+61412345678';

    $whatsapp = new Recipient;
    $whatsapp['messageID'] = 'msg-fanout-wa';
    $whatsapp['channel'] = 'whatsapp';
    $whatsapp['to'] = '+61412345678';

    $data = new Data;
    $data['recipients'] = [$sms, $whatsapp];
    $data['status'] = 'QUEUED';

    $response = new MessageSendResponse;
    $response['data'] = $data;

    $message = SentMessage::create()
        ->to('+61412345678')
        ->template('otp')
        ->channel(['sms', 'whatsapp']);

    (new LogSentMessage)->handle(new MessageSent(message: $message, response: $response, connectionName: 'default'));

    expect(SentLog::count())->toBe(2)
        ->and(SentLog::where('message_id', 'msg-fanout-sms')->first()?->channel)->toBe('sms')
        ->and(SentLog::where('message_id', 'msg-fanout-wa')->first()?->channel)->toBe('whatsapp');
});

it('creates a Queued row when a recipient entry has no messageID', function () {
    $recipient = new Recipient;
    $recipient['channel'] = 'sms';
    $recipient['to'] = '+61412345678';
    // messageID deliberately left unset.

    $data = new Data;
    $data['recipients'] = [$recipient];
    $data['status'] = 'QUEUED';

    $response = new MessageSendResponse;
    $response['data'] = $data;

    $message = SentMessage::create()
        ->to('+61412345678')
        ->template('otp')
        ->channel('sms');

    (new LogSentMessage)->handle(new MessageSent(message: $message, response: $response, connectionName: 'default'));

    $log = SentLog::first();

    expect(SentLog::count())->toBe(1)
        ->and($log?->message_id)->toBeNull()
        ->and($log?->status->value)->toBe('queued')
        ->and($log?->recipient)->toBe('+61412345678')
        ->and($log?->channel)->toBe('sms');
});

it('fills metadata on existing placeholder when webhook beat the job (race scenario)', function () {
    // SyncMessageStatus creates a placeholder row when webhook arrives early
    SentLog::updateOrCreate(
        ['message_id' => 'msg-race'],
        ['status' => 'delivered', 'recipient' => '+61412345678', 'channel' => 'sms'],
    );

    $message = SentMessage::create()
        ->to('+61412345678')
        ->template('otp')
        ->channel('sms');

    (new LogSentMessage)->handle(new MessageSent(
        message: $message,
        response: makeSendResponse('msg-race'),
        connectionName: 'default',
    ));

    $log = SentLog::where('message_id', 'msg-race')->first();

    // Status should NOT be overwritten — the webhook's 'delivered' is preserved
    expect($log?->status->value)->toBe('delivered')
        ->and($log?->recipient)->toBe('+61412345678')
        ->and($log?->template_name)->toBe('otp')
        ->and($log?->channel)->toBe('sms');
});
