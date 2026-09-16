<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Sujip\SentDm\Models\SentOptOut;

it('can be created with fillable attributes', function () {
    $record = SentOptOut::create([
        'phone_number' => '+61412345678',
        'opted_out' => true,
        'reason' => 'STOP',
        'last_opted_out_at' => now(),
    ]);

    expect($record->phone_number)->toBe('+61412345678')
        ->and($record->opted_out)->toBeTrue()
        ->and($record->reason)->toBe('STOP');
});

it('casts opted_out as boolean', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    expect(SentOptOut::first()?->opted_out)->toBeTrue();
});

it('casts last_opted_out_at and last_opted_in_at as Carbon', function () {
    $now = now();
    SentOptOut::create([
        'phone_number' => '+61412345678',
        'opted_out' => true,
        'last_opted_out_at' => $now,
        'last_opted_in_at' => $now,
    ]);

    $record = SentOptOut::first();
    expect($record?->last_opted_out_at)->not->toBeNull()
        ->and($record?->last_opted_in_at)->not->toBeNull();
});

it('enforces unique phone_number', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => false]);
})->throws(QueryException::class);

it('updateOrCreate toggles opt-out state', function () {
    SentOptOut::updateOrCreate(
        ['phone_number' => '+61412345678'],
        ['opted_out' => true, 'reason' => 'STOP', 'last_opted_out_at' => now()],
    );

    SentOptOut::updateOrCreate(
        ['phone_number' => '+61412345678'],
        ['opted_out' => false, 'last_opted_in_at' => now()],
    );

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeFalse();
});

// isOptedOut / recordOptOut / recordOptIn: the shared gate every opt-out read and
// write in the package goes through now, instead of each call site rolling its own.

it('isOptedOut returns false for an unknown phone', function () {
    expect(SentOptOut::isOptedOut('+61412345678'))->toBeFalse();
});

it('isOptedOut returns false for an empty phone', function () {
    expect(SentOptOut::isOptedOut(''))->toBeFalse();
});

it('isOptedOut reflects recordOptOut and recordOptIn', function () {
    SentOptOut::recordOptOut('+61412345678', 'STOP');
    expect(SentOptOut::isOptedOut('+61412345678'))->toBeTrue();

    SentOptOut::recordOptIn('+61412345678');
    expect(SentOptOut::isOptedOut('+61412345678'))->toBeFalse();
});

it('recordOptOut and recordOptIn do nothing for an empty phone', function () {
    SentOptOut::recordOptOut('', 'STOP');
    SentOptOut::recordOptIn('');

    expect(SentOptOut::count())->toBe(0);
});
