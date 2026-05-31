<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Sujip\SentDm\Channels\SentChannel;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Listeners\LogSentMessage;
use Sujip\SentDm\Listeners\ProcessInboundOptOut;
use Sujip\SentDm\Listeners\SyncMessageStatus;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;
use Sujip\SentDm\SentServiceProvider;

it('merges the sent config', function () {
    expect(config('sent.default'))->toBe('default');
});

it('registers all expected config keys', function () {
    expect(config('sent'))->toHaveKeys([
        'default',
        'connections',
        'default_channel',
        'queue',
        'webhook',
        'cache',
        'sandbox',
        'logging',
        'opt_out',
    ]);
});

it('registers a default connection in config', function () {
    expect(config('sent.connections.default'))->toBeArray();
});

it('binds SentManager as singleton', function () {
    $a = app(SentManager::class);
    $b = app(SentManager::class);

    expect($a)->toBeInstanceOf(SentManager::class);
    expect($a)->toBe($b);
});

it('binds SentChannel as transient so fake() swap takes effect', function () {
    $a = app(SentChannel::class);
    $b = app(SentChannel::class);

    expect($a)->toBeInstanceOf(SentChannel::class);
    expect($a)->not->toBe($b);
});

it('registers the sent alias pointing at SentManager', function () {
    expect(app('sent'))->toBeInstanceOf(SentManager::class);
});

it('resolves a default Sent driver via SentManager', function () {
    $manager = app(SentManager::class);

    expect($manager->connection())->toBeInstanceOf(Sent::class);
});

it('createDriver() throws when custom creator returns non-Sent instance', function () {
    $manager = app(SentManager::class);
    $manager->extend('bad', fn () => new stdClass);

    expect(fn () => $manager->connection('bad'))->toThrow(InvalidArgumentException::class);
});

it('registers logging listeners when sent.logging.enabled is true', function () {
    config()->set('sent.logging.enabled', true);

    (new SentServiceProvider(app()))->boot();

    $listeners = Event::getRawListeners()[MessageSent::class] ?? [];
    $classes = array_map(fn ($l) => is_string($l) ? $l : '', $listeners);
    expect(in_array(LogSentMessage::class, $classes))->toBeTrue()
        ->and(in_array(SyncMessageStatus::class, $classes))->toBeTrue();
});

it('registers opt-out listener when sent.opt_out.enabled is true', function () {
    config()->set('sent.opt_out.enabled', true);

    (new SentServiceProvider(app()))->boot();

    $listeners = Event::getRawListeners()[MessageReceived::class] ?? [];
    $classes = array_map(fn ($l) => is_string($l) ? $l : '', $listeners);
    expect(in_array(ProcessInboundOptOut::class, $classes))->toBeTrue();
});

it('connection() throws RuntimeException when driver resolves to non-Sent type', function () {
    $manager = app(SentManager::class);

    // Bypass createDriver() type gate by injecting directly into the driver cache
    $reflection = new ReflectionClass($manager);
    $prop = $reflection->getProperty('drivers');
    $prop->setAccessible(true);
    $prop->setValue($manager, ['fake-driver' => new stdClass]);

    expect(fn () => $manager->connection('fake-driver'))->toThrow(RuntimeException::class);
});
