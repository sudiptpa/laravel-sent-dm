<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
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

    expect($log->loggable())->toBeInstanceOf(MorphTo::class);
});

it('can be updated by message_id', function () {
    SentLog::create(['recipient' => '+61412345678', 'message_id' => 'msg-x', 'status' => SentLogStatus::Queued]);

    SentLog::where('message_id', 'msg-x')->update(['status' => SentLogStatus::Delivered->value]);

    expect(SentLog::where('message_id', 'msg-x')->first()?->status)->toBe(SentLogStatus::Delivered);
});

// Query scopes ---------------------------------------------------------------

it('scopeForConnection() filters by connection name', function () {
    SentLog::create(['recipient' => '+61400000001', 'connection' => 'acme', 'status' => SentLogStatus::Sent]);
    SentLog::create(['recipient' => '+61400000002', 'connection' => 'default', 'status' => SentLogStatus::Sent]);

    expect(SentLog::forConnection('acme')->count())->toBe(1);
});

it('scopeForChannel() filters by channel', function () {
    SentLog::create(['recipient' => '+61400000001', 'channel' => 'sms', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000002', 'channel' => 'whatsapp', 'status' => SentLogStatus::Delivered]);

    expect(SentLog::forChannel('sms')->count())->toBe(1);
    expect(SentLog::forChannel('whatsapp')->count())->toBe(1);
});

it('scopeForTemplate() filters by template name', function () {
    SentLog::create(['recipient' => '+61400000001', 'template_name' => 'otp', 'status' => SentLogStatus::Sent]);
    SentLog::create(['recipient' => '+61400000002', 'template_name' => 'welcome', 'status' => SentLogStatus::Sent]);

    expect(SentLog::forTemplate('otp')->count())->toBe(1);
});

it('scopeForStatus() filters by SentLogStatus enum', function () {
    SentLog::create(['recipient' => '+61400000001', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000002', 'status' => SentLogStatus::Failed]);

    expect(SentLog::forStatus(SentLogStatus::Delivered)->count())->toBe(1);
});

it('scopeForStatus() filters by raw string', function () {
    SentLog::create(['recipient' => '+61400000001', 'status' => SentLogStatus::Sent]);
    SentLog::create(['recipient' => '+61400000002', 'status' => SentLogStatus::Failed]);

    expect(SentLog::forStatus('sent')->count())->toBe(1);
});

it('scopeForRecipient() filters by phone number', function () {
    SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000001', 'status' => SentLogStatus::Delivered]);

    expect(SentLog::forRecipient('+61412345678')->count())->toBe(1);
});

it('scopeWhereSentBetween() filters by created_at range', function () {
    SentLog::forceCreate(['recipient' => '+61400000001', 'status' => 'sent', 'created_at' => now()->subDays(10), 'updated_at' => now()]);
    SentLog::forceCreate(['recipient' => '+61400000002', 'status' => 'sent', 'created_at' => now()->subDays(3), 'updated_at' => now()]);
    SentLog::forceCreate(['recipient' => '+61400000003', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);

    expect(SentLog::whereSentBetween(now()->subDays(5), now())->count())->toBe(2);
});

it('scopeGroupByStatus() returns status counts', function () {
    SentLog::create(['recipient' => '+61400000001', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000002', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000003', 'status' => SentLogStatus::Failed]);

    $rows = SentLog::groupByStatus()->get();

    expect($rows)->toHaveCount(2);
});

it('scopes compose together', function () {
    SentLog::create(['recipient' => '+61400000001', 'connection' => 'acme', 'channel' => 'sms', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000002', 'connection' => 'acme', 'channel' => 'whatsapp', 'status' => SentLogStatus::Delivered]);
    SentLog::create(['recipient' => '+61400000003', 'connection' => 'default', 'channel' => 'sms', 'status' => SentLogStatus::Delivered]);

    expect(SentLog::forConnection('acme')->forChannel('sms')->count())->toBe(1);
});
