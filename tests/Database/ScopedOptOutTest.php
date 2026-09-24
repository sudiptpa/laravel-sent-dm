<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sujip\SentDm\Contracts\ResolvesOptOutScope;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Listeners\ProcessInboundOptOut;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Support\OptOutScope;
use Sujip\SentDm\Webhooks\WebhookPayload;

it('keeps consent separate for the same contact across tenants', function () {
    SentOptOut::recordOptOut('+61412345678', 'STOP', 'tenant-a');
    SentOptOut::recordOptOut('+61412345678', 'STOP', 'tenant-b');
    SentOptOut::recordOptIn('+61412345678', 'tenant-b');

    expect(SentOptOut::isOptedOut('+61412345678', 'tenant-a'))->toBeTrue()
        ->and(SentOptOut::isOptedOut('+61412345678', 'tenant-b'))->toBeFalse()
        ->and(SentOptOut::isOptedOut('+61412345678'))->toBeTrue()
        ->and(SentOptOut::count())->toBe(2);
});

it('preserves global blocks when a tenant records an opt-in', function () {
    SentOptOut::recordOptOut('+61412345678', 'STOP');
    SentOptOut::recordOptIn('+61412345678', 'tenant-a');

    expect(SentOptOut::isOptedOut('+61412345678', 'tenant-a'))->toBeTrue()
        ->and(SentOptOut::isOptedOut('+61412345678', 'tenant-b'))->toBeTrue();
});

it('rejects an invalid explicit consent scope', function (string $scope) {
    SentOptOut::recordOptOut('+61412345678', 'STOP', $scope);
})->with(['empty' => '', 'whitespace' => '  ', 'too long' => str_repeat('a', 192)])
    ->throws(InvalidArgumentException::class);

it('rejects resolver classes that do not implement the contract', function () {
    config(['sent.opt_out.scope_resolver' => stdClass::class]);
    OptOutScope::resolver();
})->throws(InvalidArgumentException::class);

it('uses the application resolver for inbound consent', function () {
    $resolver = new class implements ResolvesOptOutScope
    {
        public function forMessage(SentMessage $message, string $connection): string
        {
            return 'tenant-a';
        }

        public function forWebhook(WebhookPayload $payload): string
        {
            return 'tenant-b';
        }
    };
    app()->instance($resolver::class, $resolver);
    config(['sent.opt_out.scope_resolver' => $resolver::class]);
    SentOptOut::recordOptOut('+61412345678', 'STOP', 'tenant-a');

    foreach (['STOP', 'START'] as $keyword) {
        (new ProcessInboundOptOut)->handle(new MessageReceived(WebhookPayload::fromArray([
            'event' => 'message.received',
            'payload' => ['from' => '+61412345678', 'text' => $keyword],
        ])));
    }

    expect(SentOptOut::isOptedOut('+61412345678', 'tenant-a'))->toBeTrue()
        ->and(SentOptOut::isOptedOut('+61412345678', 'tenant-b'))->toBeFalse()
        ->and(SentOptOut::where('scope', '')->exists())->toBeFalse();
});

it('upgrades existing consent records without losing their protection', function () {
    $migration = require __DIR__.'/../../database/migrations/2026_09_17_000000_add_scope_to_sent_opt_outs.php';
    $migration->down();
    DB::table('sent_opt_outs')->insert(['phone_number' => '+61412345678', 'opted_out' => true, 'reason' => 'STOP']);
    $migration->up();

    expect(SentOptOut::first()->scope)->toBe('')
        ->and(SentOptOut::isOptedOut('+61412345678', 'tenant-a'))->toBeTrue();
});

it('refuses a rollback that would merge tenant consent', function () {
    SentOptOut::recordOptOut('+61412345678', 'STOP', 'tenant-a');
    $migration = require __DIR__.'/../../database/migrations/2026_09_17_000000_add_scope_to_sent_opt_outs.php';

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::hasColumn('sent_opt_outs', 'scope'))->toBeTrue()
        ->and(SentOptOut::isOptedOut('+61412345678', 'tenant-a'))->toBeTrue();
});

it('rejects a resolver binding with the wrong type', function () {
    $resolver = new class implements ResolvesOptOutScope
    {
        public function forMessage(SentMessage $message, string $connection): string
        {
            return 'tenant-a';
        }

        public function forWebhook(WebhookPayload $payload): string
        {
            return 'tenant-a';
        }
    };
    config(['sent.opt_out.scope_resolver' => $resolver::class]);
    app()->instance($resolver::class, new stdClass);

    OptOutScope::resolver();
})->throws(InvalidArgumentException::class);
