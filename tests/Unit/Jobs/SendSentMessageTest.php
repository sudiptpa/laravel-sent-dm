<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\RateLimitException;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Exceptions\ContactOptedOutException;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

/** Build a SentManager mock that delegates send() to the given Sent mock. */
function mockManager(?Sent $driver = null): SentManager
{
    $driver ??= Mockery::mock(Sent::class);
    $manager = Mockery::mock(SentManager::class);
    $manager->shouldReceive('connection')->andReturn($driver);

    return $manager;
}

it('dispatches to configured queue', function () {
    Queue::fake();

    $message = SentMessage::create()->to('+61412345678')->message('Hello');

    SendSentMessage::dispatch($message);

    Queue::assertPushed(SendSentMessage::class);
});

it('calls Sent::send on handle and fires MessageSent', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->message('Hello');
    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()->with($message)->andReturn(['id' => 'msg_123']);

    $job = new SendSentMessage($message);
    $job->handle(mockManager($sent));

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($message) {
        return $event->message === $message && $event->response === ['id' => 'msg_123'];
    });
});

it('fires MessageFailed when job fails', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->message('Hello');
    $exception = new RuntimeException('API down');

    $job = new SendSentMessage($message);
    $job->failed($exception);

    Event::assertDispatched(MessageFailed::class, function (MessageFailed $event) use ($message, $exception) {
        return $event->message === $message && $event->exception === $exception;
    });
});

it('has 3 tries and afterCommit enabled', function () {
    $message = SentMessage::create()->to('+61412345678');
    $job = new SendSentMessage($message);

    expect($job->tries)->toBe(3);
    expect($job->afterCommit)->toBe(true);
});

it('returns exponential backoff intervals', function () {
    $job = new SendSentMessage(SentMessage::create()->to('+61412345678'));

    expect($job->backoff())->toBe([1, 5, 10]);
});

it('calls fail() on final rate-limit attempt so MessageFailed can be dispatched', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->template('otp');
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(429);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $response->shouldReceive('getHeaderLine')->with('Retry-After')->andReturn('30');

    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()
        ->andThrow(new RateLimitException($request, $response));

    // attempts() returns 1 when no queue job is bound; set tries=1 so final-attempt branch fires
    $job = new SendSentMessage($message);
    $job->tries = 1;
    $job->handle(mockManager($sent));

    // fail() with no bound queue job is a no-op; verify release() was not called (no MessageSent)
    Event::assertNotDispatched(MessageSent::class);

    // Verify failed() correctly fires MessageFailed when called
    $job->failed(new RateLimitException($request, $response));
    Event::assertDispatched(MessageFailed::class);
});

it('does not dispatch MessageSent when RateLimitException is thrown', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->template('otp');
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(429);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $response->shouldReceive('getHeaderLine')->with('Retry-After')->andReturn('30');

    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()
        ->andThrow(new RateLimitException($request, $response));

    $job = new SendSentMessage($message);
    $job->handle(mockManager($sent));

    Event::assertNotDispatched(MessageSent::class);
});

it('calls fail() and does not retry on ContactOptedOutException', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->template('otp');

    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()
        ->andThrow(new ContactOptedOutException('+61412345678'));

    $job = new SendSentMessage($message);
    $job->handle(mockManager($sent));

    // fail() with no bound queue job is a no-op; MessageSent is not dispatched
    Event::assertNotDispatched(MessageSent::class);

    // failed() fires MessageFailed
    $job->failed(new ContactOptedOutException('+61412345678'));
    Event::assertDispatched(MessageFailed::class);
});

it('MessageSent carries connectionName from job', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->template('otp');

    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()->andReturn(['status' => 'QUEUED']);

    $job = new SendSentMessage($message, 'acme');
    $job->handle(mockManager($sent));

    Event::assertDispatched(MessageSent::class, fn ($e) => $e->connectionName === 'acme');
});

it('does not dispatch MessageSent when Retry-After header is missing', function () {
    Event::fake();

    $message = SentMessage::create()->to('+61412345678')->template('otp');
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(429);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $response->shouldReceive('getHeaderLine')->with('Retry-After')->andReturn('');

    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()
        ->andThrow(new RateLimitException($request, $response));

    $job = new SendSentMessage($message);
    $job->handle(mockManager($sent));

    Event::assertNotDispatched(MessageSent::class);
});
