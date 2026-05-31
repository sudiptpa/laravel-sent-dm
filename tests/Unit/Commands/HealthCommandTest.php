<?php

declare(strict_types=1);

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
