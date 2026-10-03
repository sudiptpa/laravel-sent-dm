<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Webhooks\WebhookTestResponse;
use SentDm\Webhooks\WebhookTestResponse\Data as WebhookTestData;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeWebhookTestResponse(?bool $success = true, ?string $message = 'Delivered'): WebhookTestResponse
{
    $data = new WebhookTestData;
    $data['success'] = $success;
    $data['message'] = $message;

    $response = new WebhookTestResponse;
    $response['data'] = $data;

    return $response;
}

it('sends a webhook test event', function () {
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('test')
        ->once()
        ->with('wh_123', 'message.delivered', 'idem_1', true)
        ->andReturn(fakeWebhookTestResponse(message: 'Accepted'));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:test', [
        'id' => 'wh_123',
        '--event' => 'message.delivered',
        '--idempotency-key' => 'idem_1',
        '--sandbox' => true,
    ])
        ->expectsOutputToContain('Test event sent')
        ->expectsOutputToContain('Accepted')
        ->assertExitCode(0);
});

it('shows endpoint issues without failing the command', function () {
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('test')
        ->once()
        ->with('wh_123', 'message.sent', null, false)
        ->andReturn(fakeWebhookTestResponse(false, 'Endpoint returned 500'));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:test', ['id' => 'wh_123'])
        ->expectsOutputToContain('endpoint reported an issue')
        ->expectsOutputToContain('Endpoint returned 500')
        ->assertExitCode(0);
});

it('fails when the webhook test response has no data', function () {
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('test')->once()->andReturn(new WebhookTestResponse);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:test', ['id' => 'wh_123'])->assertExitCode(1);
});

it('shows failure when webhook test API call fails', function () {
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $stream = Mockery::mock(StreamInterface::class);

    $response->shouldReceive('getStatusCode')->andReturn(401);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('test')->once()->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:test', ['id' => 'wh_123'])->assertExitCode(1);
});
