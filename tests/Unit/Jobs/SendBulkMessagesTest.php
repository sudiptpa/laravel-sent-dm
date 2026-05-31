<?php

declare(strict_types=1);

use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Jobs\SendBulkMessages;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\SentBulkDispatcher;

it('dispatches via SentBulkDispatcher', function () {
    Queue::fake();

    $dispatcher = new SentBulkDispatcher(['+61412345678', '+61412345679']);
    $dispatcher->message('Hello')->dispatch();

    Queue::assertPushed(SendBulkMessages::class);
});

it('fans out one SendSentMessage per recipient', function () {
    $recipients = ['+61412345678', '+61412345679', '+61412345670'];
    $captured = [];

    $pendingBatch = Mockery::mock(PendingBatch::class);
    $pendingBatch->shouldReceive('allowFailures')->once()->andReturnSelf();
    $pendingBatch->shouldReceive('dispatch')->once()->andReturnNull();

    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('batch')
        ->once()
        ->withArgs(function (array $jobs) use (&$captured) {
            $captured = $jobs;

            return true;
        })
        ->andReturn($pendingBatch);

    $template = SentMessage::create()->message('Hello');

    (new SendBulkMessages($recipients, $template))->handle($bus);

    expect($captured)->toHaveCount(3);
});

it('SentBulkDispatcher is immutable', function () {
    $base = new SentBulkDispatcher(['+61412345678']);
    $withTemplate = $base->template('otp');
    $withChannel = $base->channel('sms');

    expect($withTemplate)->not->toBe($base);
    expect($withChannel)->not->toBe($base);
});

it('SentBulkDispatcher message() is immutable', function () {
    $base = new SentBulkDispatcher(['+61412345678']);
    $with = $base->message('Hello');

    expect($with)->not->toBe($base);
});

it('SentBulkDispatcher with() sets template data immutably', function () {
    $base = new SentBulkDispatcher(['+61412345678']);
    $with = $base->with(['code' => '1234']);

    expect($with)->not->toBe($base);
});

it('SentBulkDispatcher usingProfile() is immutable', function () {
    $base = new SentBulkDispatcher(['+61412345678']);
    $with = $base->usingProfile('prof-1');

    expect($with)->not->toBe($base);
});

it('SentBulkDispatcher dispatch() throws when recipients empty', function () {
    (new SentBulkDispatcher([]))->dispatch();
})->throws(InvalidArgumentException::class);

it('failed() dispatches MessageFailed event', function () {
    Event::fake();

    $job = new SendBulkMessages(['+61412345678'], SentMessage::create()->template('otp'));
    $job->failed(new RuntimeException('batch failed'));

    Event::assertDispatched(MessageFailed::class);
});
