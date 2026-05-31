<?php

declare(strict_types=1);

use Sujip\SentDm\Channels\SentChannel;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

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

it('connection() throws RuntimeException when driver resolves to non-Sent type', function () {
    $manager = app(SentManager::class);

    // Bypass createDriver() type gate by injecting directly into the driver cache
    $reflection = new ReflectionClass($manager);
    $prop = $reflection->getProperty('drivers');
    $prop->setAccessible(true);
    $prop->setValue($manager, ['fake-driver' => new stdClass]);

    expect(fn () => $manager->connection('fake-driver'))->toThrow(RuntimeException::class);
});
