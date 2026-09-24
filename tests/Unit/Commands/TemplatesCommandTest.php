<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Client;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\RequestOptions;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

/**
 * A Sent driver backed by a test transport. Used here instead of mocking
 * Templates::get() returns SDK page objects that keep their client instance.
 *
 * @param  array<int, array<string, mixed>>  $templates
 */
function sentDriverWithTemplates(array $templates = []): Sent
{
    $body = json_encode([
        'success' => true,
        'data' => ['templates' => $templates],
        'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($body) implements ClientInterface
    {
        public function __construct(private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return new Sent(new Client(apiKey: 'test', requestOptions: $opts));
}

it('lists templates in a table', function () {
    $driver = sentDriverWithTemplates([
        ['id' => 'tpl-1', 'name' => 'otp_verify', 'category' => 'UTILITY', 'status' => 'APPROVED', 'channels' => ['sms']],
        ['id' => 'tpl-2', 'name' => 'welcome', 'category' => 'UTILITY', 'status' => 'APPROVED', 'channels' => ['sms']],
    ]);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('otp_verify')
        ->expectsOutputToContain('welcome')
        ->assertExitCode(0);
});

it('skips a template entry the SDK could not hydrate into a Template object', function () {
    $driver = sentDriverWithTemplates([
        'not-an-object',
        ['id' => 'tpl-1', 'name' => 'otp_verify', 'category' => 'UTILITY', 'status' => 'APPROVED', 'channels' => ['sms']],
    ]);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('otp_verify')
        ->assertExitCode(0);
});

it('shows info when no templates exist', function () {
    $driver = sentDriverWithTemplates([]);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('No templates')
        ->assertExitCode(0);
});

it('shows failure on API exception', function () {
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(401);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);

    $resource = Mockery::mock(Templates::class);
    $resource->shouldReceive('page')->once()->andReturn($resource);
    $resource->shouldReceive('perPage')->once()->andReturn($resource);
    $resource->shouldReceive('get')->andThrow(new AuthenticationException($request, $response));

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('templates')->once()->andReturn($resource);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')->assertExitCode(1);
});
