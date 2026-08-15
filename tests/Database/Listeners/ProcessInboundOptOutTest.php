<?php

declare(strict_types=1);

use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Listeners\ProcessInboundOptOut;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Webhooks\WebhookPayload;

function inboundPayload(string $from, string $text): MessageReceived
{
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.received',
        'payload' => [
            'from' => $from,
            'to' => '+61900000000',
            'text' => $text,
            'channel' => 'sms',
        ],
    ]);

    return new MessageReceived($payload);
}

it('records opt-out on STOP', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'STOP'));

    $record = SentOptOut::where('phone_number', '+61412345678')->first();
    expect($record?->opted_out)->toBeTrue()
        ->and($record?->reason)->toBe('STOP');
});

it('records opt-out on UNSUBSCRIBE', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'UNSUBSCRIBE'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeTrue();
});

it('records opt-out on CANCEL', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'CANCEL'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeTrue();
});

it('records opt-out on END', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'END'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeTrue();
});

it('records opt-out on QUIT', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'QUIT'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeTrue();
});

it('records opt-in on START', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true, 'reason' => 'STOP']);

    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'START'));

    $record = SentOptOut::where('phone_number', '+61412345678')->first();
    expect($record?->opted_out)->toBeFalse()
        ->and($record?->reason)->toBeNull();
});

it('records opt-in on YES', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'YES'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeFalse();
});

it('records opt-in on UNSTOP', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'UNSTOP'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeFalse();
});

it('is case-insensitive', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'stop'));

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeTrue();
});

it('ignores messages that are not opt-out or opt-in keywords', function () {
    (new ProcessInboundOptOut)->handle(inboundPayload('+61412345678', 'Hello there'));

    expect(SentOptOut::count())->toBe(0);
});

it('skips when sender phone is absent', function () {
    $payload = WebhookPayload::fromArray([
        'field' => 'message',
        'event' => 'message.received',
        'payload' => ['text' => 'STOP'],
    ]);

    (new ProcessInboundOptOut)->handle(new MessageReceived($payload));

    expect(SentOptOut::count())->toBe(0);
});
