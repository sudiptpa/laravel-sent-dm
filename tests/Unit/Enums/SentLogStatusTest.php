<?php

declare(strict_types=1);

use Sujip\SentDm\Enums\SentLogStatus;

it('has the expected string values', function () {
    expect(SentLogStatus::Queued->value)->toBe('queued')
        ->and(SentLogStatus::Sent->value)->toBe('sent')
        ->and(SentLogStatus::Delivered->value)->toBe('delivered')
        ->and(SentLogStatus::Failed->value)->toBe('failed')
        ->and(SentLogStatus::Read->value)->toBe('read');
});

it('can be constructed from a string value', function () {
    expect(SentLogStatus::from('delivered'))->toBe(SentLogStatus::Delivered);
});
