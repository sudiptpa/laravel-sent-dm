<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Me\MeGetResponse;
use SentDm\Me\MeGetResponse\Data;
use SentDm\Me\MeGetResponse\Data\Channels;
use SentDm\Me\MeGetResponse\Data\Channels\Rcs;
use SentDm\Me\MeGetResponse\Data\Channels\SMS;
use SentDm\Me\MeGetResponse\Data\Channels\Whatsapp;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeMeResponse(string $type = 'organization', string $name = 'Acme'): MeGetResponse
{
    $sms = new SMS;
    $sms['configured'] = true;
    $sms['phoneNumber'] = '+61400000000';

    $wa = new Whatsapp;
    $wa['configured'] = false;

    $rcs = new Rcs;
    $rcs['configured'] = false;

    $channels = new Channels;
    $channels['sms'] = $sms;
    $channels['whatsapp'] = $wa;
    $channels['rcs'] = $rcs;

    $data = new Data;
    $data['type'] = $type;
    $data['name'] = $name;
    $data['email'] = 'admin@example.com';
    $data['channels'] = $channels;

    $response = new MeGetResponse;
    $response['data'] = $data;

    return $response;
}

it('displays account info on success', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('account')->once()->andReturn(fakeMeResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')
        ->expectsOutputToContain('Connected')
        ->expectsOutputToContain('Acme')
        ->expectsOutputToContain('admin@example.com')
        ->assertExitCode(0);
});

it('shows Laravel package diagnostics without exposing secrets', function () {
    config()->set('sent.connections.default.api_key', 'sent_test_secret');
    config()->set('sent.queue.connection', 'redis');
    config()->set('sent.queue.name', 'sent');
    config()->set('sent.queue.tries', 5);
    config()->set('sent.queue.backoff', [2, '8']);
    config()->set('sent.webhook.enabled', true);
    config()->set('sent.webhook.secret', 'whsec_secret');
    config()->set('sent.webhook.path', 'hooks/sent');
    config()->set('sent.webhook.dedup_ttl', 120);
    config()->set('sent.logging.enabled', true);
    config()->set('sent.opt_out.enabled', true);
    config()->set('sent.opt_out.guard', true);

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('account')->once()->andReturn(fakeMeResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')
        ->expectsOutputToContain('Configuration')
        ->expectsOutputToContain('configured')
        ->expectsOutputToContain('redis')
        ->expectsOutputToContain('sent')
        ->expectsOutputToContain('5')
        ->expectsOutputToContain('2, 8')
        ->expectsOutputToContain('Webhook')
        ->expectsOutputToContain('120')
        ->expectsOutputToContain('Database features')
        ->doesntExpectOutputToContain('sent_test_secret')
        ->doesntExpectOutputToContain('whsec_secret')
        ->assertExitCode(0);
});

it('shows safe fallbacks for invalid local diagnostics config', function () {
    config()->set('sent.queue.connection', null);
    config()->set('sent.queue.name', null);
    config()->set('sent.queue.tries', 'bad');
    config()->set('sent.queue.backoff', 'bad');
    config()->set('sent.webhook.dedup_ttl', 'bad');

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('account')->once()->andReturn(fakeMeResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')
        ->expectsOutputToContain('default')
        ->expectsOutputToContain('invalid, using 3')
        ->expectsOutputToContain('invalid, using 1, 5, 10')
        ->expectsOutputToContain('invalid, using 86400')
        ->assertExitCode(0);
});

it('continues when database table checks are unavailable', function () {
    config()->set('sent.logging.enabled', true);

    Schema::shouldReceive('hasTable')
        ->once()
        ->with('sent_logs')
        ->andThrow(new RuntimeException('database unavailable'));

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('account')->once()->andReturn(fakeMeResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')
        ->expectsOutputToContain('table check unavailable')
        ->assertExitCode(0);
});

it('shows failure on API exception', function () {
    $driver = Mockery::mock(Sent::class);
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(401);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $driver->shouldReceive('account')->once()
        ->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')->assertExitCode(1);
});

it('returns failure when response data is null', function () {
    $response = new MeGetResponse;

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('account')->once()->andReturn($response);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')->assertExitCode(1);
});
