<?php

declare(strict_types=1);

use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Models\SentLog;

it('can be created with fillable attributes', function () {
    $log = SentLog::create([
        'connection' => 'default',
        'recipient' => '+61412345678',
        'channel' => 'sms',
        'template_name' => 'otp',
        'message_id' => 'msg-001',
        'idempotency_key' => 'key-001',
        'status' => SentLogStatus::Queued,
    ]);

    expect($log->connection)->toBe('default')
        ->and($log->recipient)->toBe('+61412345678')
        ->and($log->channel)->toBe('sms')
        ->and($log->template_name)->toBe('otp')
        ->and($log->message_id)->toBe('msg-001')
        ->and($log->idempotency_key)->toBe('key-001')
        ->and($log->status)->toBe(SentLogStatus::Queued);
});

it('casts status to SentLogStatus enum', function () {
    $log = SentLog::create(['recipient' => '+61412345678', 'status' => 'delivered']);

    expect($log->fresh()?->status)->toBe(SentLogStatus::Delivered);
});

it('stores loggable polymorphic fields', function () {
    $log = SentLog::create([
        'recipient' => '+61412345678',
        'status' => SentLogStatus::Queued,
        'loggable_type' => 'App\\Models\\User',
        'loggable_id' => '42',
    ]);

    expect($log->loggable_type)->toBe('App\\Models\\User')
        ->and($log->loggable_id)->toBe('42');
});

it('loggable() returns a MorphTo relationship', function () {
    $log = SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Queued]);

    expect($log->loggable())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

it('can be updated by message_id', function () {
    SentLog::create(['recipient' => '+61412345678', 'message_id' => 'msg-x', 'status' => SentLogStatus::Queued]);

    SentLog::where('message_id', 'msg-x')->update(['status' => SentLogStatus::Delivered->value]);

    expect(SentLog::where('message_id', 'msg-x')->first()?->status)->toBe(SentLogStatus::Delivered);
});
