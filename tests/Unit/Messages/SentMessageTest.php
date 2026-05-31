<?php

declare(strict_types=1);

use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;

it('creates via static factory', function () {
    expect(SentMessage::create())->toBeInstanceOf(SentMessage::class);
});

it('is immutable — each setter returns new instance', function () {
    $original = SentMessage::create();
    $modified = $original->to('+61412345678');

    expect($modified)->not->toBe($original);
    expect($original->getRecipient())->toBeNull();
    expect($modified->getRecipient())->toBe('+61412345678');
});

it('chains all setters fluently', function () {
    $message = SentMessage::create()
        ->to('+61412345678')
        ->message('Hello world')
        ->channel('whatsapp')
        ->template('order_shipped', 'tmpl-123')
        ->with(['name' => 'Steve'])
        ->usingProfile('profile-abc');

    expect($message->getRecipient())->toBe('+61412345678');
    expect($message->getContent())->toBe('Hello world');
    expect($message->getChannel())->toBe('whatsapp');
    expect($message->getTemplateName())->toBe('order_shipped');
    expect($message->getTemplateId())->toBe('tmpl-123');
    expect($message->getTemplateData())->toBe(['name' => 'Steve']);
    expect($message->getProfileId())->toBe('profile-abc');
});

it('does not mutate original when chaining', function () {
    $base = SentMessage::create()->to('+61412345678');
    $withChannel = $base->channel('sms');
    $withTemplate = $base->template('otp');

    expect($base->getChannel())->toBeNull();
    expect($base->getTemplateName())->toBeNull();
    expect($withChannel->getChannel())->toBe('sms');
    expect($withTemplate->getTemplateName())->toBe('otp');
});

it('sets idempotency key immutably', function () {
    $original = SentMessage::create();
    $keyed = $original->idempotencyKey('order-123');

    expect($keyed)->not->toBe($original);
    expect($original->getIdempotencyKey())->toBeNull();
    expect($keyed->getIdempotencyKey())->toBe('order-123');
});

it('throws LogicException when send called without manager', function () {
    SentMessage::create()->to('+61412345678')->send();
})->throws(LogicException::class);

it('throws LogicException when sendLater called without manager', function () {
    SentMessage::create()->to('+61412345678')->sendLater();
})->throws(LogicException::class);

it('delegates sendLater to manager dispatch', function () {
    $manager = Mockery::mock(Sent::class);
    $message = SentMessage::create()
        ->withManager($manager)
        ->to('+61412345678');

    $manager->shouldReceive('dispatch')->once()->with($message);

    $message->sendLater();
});

it('delegates send to injected manager', function () {
    $manager = Mockery::mock(Sent::class);
    $message = SentMessage::create()
        ->withManager($manager)
        ->to('+61412345678');

    $manager->shouldReceive('send')->once()->with($message)->andReturn(null);

    $message->send();
});

it('withoutManager clears the manager clone without mutating original', function () {
    $manager = Mockery::mock(Sent::class);
    $withManager = SentMessage::create()->withManager($manager);
    $without = $withManager->withoutManager();

    expect($without)->not->toBe($withManager);
    // withoutManager should not throw — if manager were still set, send() would delegate
    // We verify it by confirming send() now throws LogicException (no manager)
    $without->to('+61412345678')->send();
})->throws(LogicException::class);

it('survives serialize/unserialize round-trip without manager', function () {
    $original = SentMessage::create()
        ->to('+61412345678')
        ->message('Hello')
        ->channel('sms')
        ->template('otp', 'tpl-1')
        ->with(['code' => '1234'])
        ->usingProfile('prof-1')
        ->idempotencyKey('idem-1');

    $restored = unserialize(serialize($original));

    expect($restored->getRecipient())->toBe('+61412345678')
        ->and($restored->getContent())->toBe('Hello')
        ->and($restored->getChannel())->toBe('sms')
        ->and($restored->getTemplateName())->toBe('otp')
        ->and($restored->getTemplateId())->toBe('tpl-1')
        ->and($restored->getTemplateData())->toBe(['code' => '1234'])
        ->and($restored->getProfileId())->toBe('prof-1')
        ->and($restored->getIdempotencyKey())->toBe('idem-1');
});

it('sandbox() sets sandbox flag immutably', function () {
    $original = SentMessage::create();
    $sandboxed = $original->sandbox();

    expect($sandboxed)->not->toBe($original)
        ->and($original->getSandbox())->toBeNull()
        ->and($sandboxed->getSandbox())->toBeTrue();
});

it('sandbox(false) clears the sandbox flag', function () {
    $message = SentMessage::create()->sandbox()->sandbox(false);

    expect($message->getSandbox())->toBeFalse();
});
