<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Sujip\SentDm\Facades\Sent;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\SentBulkDispatcher;
use Sujip\SentDm\SentFake;

it('Sent::fake() swaps the facade with a SentFake instance', function () {
    $fake = Sent::fake();

    expect($fake)->toBeInstanceOf(SentFake::class);
    expect(Sent::getFacadeRoot())->toBeInstanceOf(SentFake::class);
});

it('records sent messages', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();

    $fake->assertSent(fn (SentMessage $m) => $m->getRecipient() === '+61412345678');
    $fake->assertSentCount(1);
    $fake->assertNothingQueued();
    expect($fake->hasSent())->toBeTrue();
    expect($fake->hasQueued())->toBeFalse();
});

it('assertSentTo passes for matching recipient', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();

    $fake->assertSentTo('+61412345678');
});

it('assertSentTo fails for non-matching recipient', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();

    expect(fn () => $fake->assertSentTo('+44123456789'))->toThrow(AssertionFailedError::class);
});

it('assertSentWithTemplate passes for matching template', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp_verify')->send();

    $fake->assertSentWithTemplate('otp_verify');
});

it('assertSentWithTemplate filters past non-matching templates', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();
    Sent::to('+61412345679')->template('promo')->send();

    $fake->assertSentWithTemplate('promo');
});

it('assertSentCount asserts the exact count', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();
    Sent::to('+61412345679')->template('otp')->send();

    $fake->assertSentCount(2);
});

it('assertNothingSent passes when nothing was sent', function () {
    $fake = Sent::fake();

    $fake->assertNothingSent();
});

it('assertNothingSent fails when a message was sent', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();

    expect(fn () => $fake->assertNothingSent())->toThrow(AssertionFailedError::class);
});

it('records queued messages via sendLater', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->sendLater();

    $fake->assertQueued(fn (SentMessage $m) => $m->getRecipient() === '+61412345678');
    $fake->assertQueuedTo('+61412345678');
    $fake->assertQueuedCount(1);
    $fake->assertNothingSent();
    expect($fake->hasQueued())->toBeTrue();
});

it('assertQueuedTo filters past non-matching recipients', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->sendLater();
    Sent::to('+61412345679')->template('otp')->sendLater();

    $fake->assertQueuedTo('+61412345679');
});

it('assertNothingQueued fails when a message was queued', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->sendLater();

    expect(fn () => $fake->assertNothingQueued())->toThrow(AssertionFailedError::class);
});

it('reset clears sent and queued', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();
    Sent::to('+61412345679')->template('otp')->sendLater();

    $fake->reset();

    $fake->assertNothingSent();
    $fake->assertNothingQueued();
});

it('connection() returns the same fake instance', function () {
    $fake = Sent::fake();

    expect(Sent::connection('tenant_a'))->toBe($fake);
});

it('bulk() returns a SentBulkDispatcher', function () {
    $fake = Sent::fake();

    expect(Sent::bulk(['+61412345678']))->toBeInstanceOf(SentBulkDispatcher::class);
});

it('sent() and queued() return the recorded messages', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->send();
    Sent::to('+61412345679')->template('otp')->sendLater();

    expect($fake->sent())->toHaveCount(1);
    expect($fake->queued())->toHaveCount(1);
});

it('assertSentTo accepts an optional callback for extra assertions', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->idempotencyKey('key-1')->send();

    $fake->assertSentTo('+61412345678', fn (SentMessage $m) => $m->getIdempotencyKey() === 'key-1');
});

it('assertQueuedCount asserts the exact queued count', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->sendLater();
    Sent::to('+61412345679')->template('otp')->sendLater();

    $fake->assertQueuedCount(2);
});

it('assertSentWithTemplate accepts an optional callback for extra assertions', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->channel('sms')->send();

    $fake->assertSentWithTemplate('otp', fn (SentMessage $m) => $m->getChannel() === 'sms');
});

it('assertQueuedTo accepts an optional callback for extra assertions', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->idempotencyKey('key-q')->sendLater();

    $fake->assertQueuedTo('+61412345678', fn (SentMessage $m) => $m->getIdempotencyKey() === 'key-q');
});

it('assertSentViaConnection passes for matching connection', function () {
    $fake = Sent::fake();

    Sent::connection('tenant_a')->to('+61412345678')->template('otp')->send();

    $fake->assertSentViaConnection('tenant_a');
    $fake->assertSentViaConnection('tenant_a', fn (SentMessage $m) => $m->getRecipient() === '+61412345678');
});

it('assertQueuedViaConnection passes for matching connection', function () {
    $fake = Sent::fake();

    Sent::connection('tenant_b')->to('+61412345678')->template('otp')->sendLater();

    $fake->assertQueuedViaConnection('tenant_b');
});

it('connection() does not mix sends across different connections', function () {
    $fake = Sent::fake();

    Sent::connection('a')->to('+61400000001')->template('otp')->send();
    Sent::connection('b')->to('+61400000002')->template('otp')->send();

    $fake->assertSentViaConnection('a', fn (SentMessage $m) => $m->getRecipient() === '+61400000001');
    $fake->assertSentViaConnection('b', fn (SentMessage $m) => $m->getRecipient() === '+61400000002');
    $fake->assertSentCount(2);
});

it('dispatch() strips manager so queued message can be safely serialized', function () {
    $fake = Sent::fake();

    Sent::to('+61412345678')->template('otp')->sendLater();

    $queued = $fake->queued();
    // withoutManager() was called — serialize()/unserialize() round-trip must not throw
    $restored = unserialize(serialize($queued[0]));
    expect($restored)->toBeInstanceOf(SentMessage::class);
});

it('__call throws BadMethodCallException for undefined API methods', function () {
    expect(fn () => Sent::fake()->account())->toThrow(BadMethodCallException::class);
});
