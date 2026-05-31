<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

it('fails when no template is provided', function () {
    app()->instance(SentManager::class, mockSentManager());

    $this->artisan('sent:test-send', ['number' => '+61412345678'])
        ->expectsOutputToContain('template')
        ->assertExitCode(1);
});

it('sends and reports success', function () {
    $driver = Mockery::mock(Sent::class);

    // Build a real SentMessage with the mock as manager so send() works
    $message = SentMessage::create()
        ->withManager($driver)
        ->to('+61412345678')
        ->template('otp');

    $driver->shouldReceive('to')->once()->andReturn($message);
    $driver->shouldReceive('send')->once()->andReturn(null);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:test-send', ['number' => '+61412345678', '--template' => 'otp'])
        ->expectsOutputToContain('queued')
        ->assertExitCode(0);
});

it('sends with sandbox flag when --sandbox is passed', function () {
    $driver = Mockery::mock(Sent::class);

    $message = SentMessage::create()
        ->withManager($driver)
        ->to('+61412345678')
        ->template('otp')
        ->sandbox();

    // to() returns sandboxed message
    $driver->shouldReceive('to')->once()->andReturn($message);
    $driver->shouldReceive('send')->once()->andReturn(null);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:test-send', ['number' => '+61412345678', '--template' => 'otp', '--sandbox' => true])
        ->expectsOutputToContain('queued')
        ->assertExitCode(0);
});

it('shows failure on API exception', function () {
    $driver = new class extends Sent
    {
        public function __construct()
        {
            // skip parent
        }

        public function to(string $recipient): SentMessage
        {
            $req = Mockery::mock(RequestInterface::class);
            $res = Mockery::mock(ResponseInterface::class);
            $res->shouldReceive('getStatusCode')->andReturn(401);
            $stream = Mockery::mock(StreamInterface::class);
            $stream->shouldReceive('__toString')->andReturn('{}');
            $stream->shouldReceive('getContents')->andReturn('{}');
            $res->shouldReceive('getBody')->andReturn($stream);
            throw new AuthenticationException($req, $res);
        }
    };

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:test-send', ['number' => '+61412345678', '--template' => 'otp'])
        ->assertExitCode(1);
});
