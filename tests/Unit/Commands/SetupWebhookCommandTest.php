<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Webhooks\WebhookNewResponse;
use SentDm\Webhooks\WebhookNewResponse\Data;
use Sujip\SentDm\Builders\WebhookBuilder;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeWebhookResponse(?string $secret = 'whsec_abc123'): WebhookNewResponse
{
    $data = new Data;
    $data['id'] = 'wh-uuid';
    $data['endpointURL'] = 'https://example.com/webhook';
    $data['signingSecret'] = $secret;

    $response = new WebhookNewResponse;
    $response['data'] = $data;

    return $response;
}

/** @return array{Sent, Webhooks, WebhookBuilder} */
function mockWebhookChain(): array
{
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);
    $builder = Mockery::mock(WebhookBuilder::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('create')->once()->andReturn($builder);
    $builder->shouldReceive('url')->once()->andReturn($builder);
    $builder->shouldReceive('events')->once()->andReturn($builder);

    return [$driver, $resource, $builder];
}

it('creates a webhook and displays the signing secret', function () {
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(fakeWebhookResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->expectsOutputToContain('Webhook created')
        ->expectsOutputToContain('whsec_abc123')
        ->expectsOutputToContain('SENT_WEBHOOK_ENABLED')
        ->assertExitCode(0);
});

it('handles response with no signing secret', function () {
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(fakeWebhookResponse(null));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->expectsOutputToContain('Webhook created')
        ->assertExitCode(0);
});

it('shows failure when data is null', function () {
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(new WebhookNewResponse);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->assertExitCode(1);
});

it('shows failure on API exception', function () {
    [$driver, , $builder] = mockWebhookChain();
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(401);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $builder->shouldReceive('save')->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->assertExitCode(1);
});
