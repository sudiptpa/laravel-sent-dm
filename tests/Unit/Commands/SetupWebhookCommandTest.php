<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Webhooks\APIResponseWebhook;
use SentDm\Webhooks\WebhookResponse;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeWebhookResponse(?string $secret = 'whsec_abc123'): APIResponseWebhook
{
    $data = new WebhookResponse;
    $data['id'] = 'wh-uuid';
    $data['endpointURL'] = 'https://example.com/webhook';
    $data['signingSecret'] = $secret;

    $response = new APIResponseWebhook;
    $response['data'] = $data;

    return $response;
}

it('creates a webhook and displays the signing secret', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('createWebhook')->once()->andReturn(fakeWebhookResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->expectsOutputToContain('Webhook created')
        ->expectsOutputToContain('whsec_abc123')
        ->expectsOutputToContain('SENT_WEBHOOK_ENABLED')
        ->assertExitCode(0);
});

it('handles response with no signing secret', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('createWebhook')->once()->andReturn(fakeWebhookResponse(null));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->expectsOutputToContain('Webhook created')
        ->assertExitCode(0);
});

it('shows failure when data is null', function () {
    $response = new APIResponseWebhook;
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('createWebhook')->once()->andReturn($response);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->assertExitCode(1);
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
    $driver->shouldReceive('createWebhook')->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->assertExitCode(1);
});
