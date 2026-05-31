<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Numbers\NumberLookupResponse;
use SentDm\Numbers\NumberLookupResponse\Data;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeLookupResponse(bool $valid = true): NumberLookupResponse
{
    $data = new Data;
    $data['isValid'] = $valid;
    $data['carrierName'] = 'Telstra';
    $data['lineType'] = 'mobile';
    $data['isVoip'] = false;
    $data['isPorted'] = false;
    $data['countryCode'] = '61';

    $response = new NumberLookupResponse;
    $response['data'] = $data;

    return $response;
}

it('displays lookup result for a valid number', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->with('+61412345678')->andReturn(fakeLookupResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:lookup', ['number' => '+61412345678'])
        ->expectsOutputToContain('Telstra')
        ->expectsOutputToContain('mobile')
        ->assertExitCode(0);
});

it('shows failure when data is null', function () {
    $response = new NumberLookupResponse;

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andReturn($response);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:lookup', ['number' => '+61412345678'])->assertExitCode(1);
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
    $driver->shouldReceive('lookup')->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:lookup', ['number' => '+61412345678'])->assertExitCode(1);
});
