<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Webhooks\APIResponseWebhook;
use SentDm\Webhooks\WebhookResponse;
use Sujip\SentDm\Builders\WebhookBuilder;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

beforeEach(function () {
    $this->secretPath = sys_get_temp_dir().'/sent-webhook-'.bin2hex(random_bytes(8)).'/webhook.env';
});

afterEach(function () {
    if (is_file($this->secretPath)) {
        unlink($this->secretPath);
    }
    if (is_dir(dirname($this->secretPath))) {
        rmdir(dirname($this->secretPath));
    }
});

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

/** @return array{Sent, Webhooks, WebhookBuilder} */
function mockWebhookChain(): array
{
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);
    $builder = Mockery::mock(WebhookBuilder::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('create')->once()->andReturn($builder);
    $builder->shouldReceive('name')->once()->andReturn($builder);
    $builder->shouldReceive('url')->once()->andReturn($builder);
    $builder->shouldReceive('events')->once()->andReturn($builder);

    return [$driver, $resource, $builder];
}

it('creates a webhook and saves the signing secret without printing it', function () {
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(fakeWebhookResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => $this->secretPath])
        ->expectsOutputToContain('Webhook created')
        ->doesntExpectOutputToContain('whsec_abc123')
        ->expectsOutputToContain('SENT_WEBHOOK_ENABLED')
        ->assertExitCode(0);

    expect(file_get_contents($this->secretPath))->toBe('SENT_WEBHOOK_SECRET="whsec_abc123"'.PHP_EOL)
        ->and(fileperms($this->secretPath) & 0777)->toBe(0600);
});

it('handles response with no signing secret', function () {
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(fakeWebhookResponse(null));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => $this->secretPath])
        ->expectsOutputToContain('Webhook created')
        ->assertExitCode(0);

    expect(file_exists($this->secretPath))->toBeFalse();
});

it('shows failure when data is null', function () {
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(new APIResponseWebhook);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => $this->secretPath])
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

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => $this->secretPath])
        ->assertExitCode(1);
});

it('refuses an existing secret file before creating a webhook', function () {
    mkdir(dirname($this->secretPath), 0700, true);
    file_put_contents($this->secretPath, 'existing environment');
    $manager = Mockery::mock(SentManager::class);
    $manager->shouldNotReceive('connection');
    app()->instance(SentManager::class, $manager);

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => $this->secretPath])
        ->assertFailed();

    expect(file_get_contents($this->secretPath))->toBe('existing environment');
});

it('refuses stream wrappers for the secret destination', function () {
    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => 'php://stdout'])
        ->assertFailed();
});

it('uses a private file under application storage by default', function () {
    app()->useStoragePath(dirname($this->secretPath));
    $this->secretPath = storage_path('app/private/sent-webhook.env');
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturn(fakeWebhookResponse());
    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook'])
        ->assertSuccessful();

    expect(is_file($this->secretPath))->toBeTrue()
        ->and(fileperms($this->secretPath) & 0777)->toBe(0600);
});

it('fails before creating a webhook if the destination directory cannot be created', function () {
    mkdir(dirname($this->secretPath), 0700, true);
    file_put_contents($this->secretPath, 'not a directory');
    $manager = Mockery::mock(SentManager::class);
    $manager->shouldNotReceive('connection');
    app()->instance(SentManager::class, $manager);

    $this->artisan('sent:setup-webhook', [
        'url' => 'https://example.com/webhook',
        '--secret-file' => $this->secretPath.'/nested/secret.env',
    ])->assertFailed();
});

class RejectSecretWrites extends php_user_filter
{
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        return PSFS_ERR_FATAL;
    }
}

it('reports failed secret writes and removes the incomplete file', function () {
    stream_filter_register('sent.reject-writes', RejectSecretWrites::class);
    [$driver, , $builder] = mockWebhookChain();
    $builder->shouldReceive('save')->once()->andReturnUsing(function () {
        foreach (get_resources('stream') as $stream) {
            if ((stream_get_meta_data($stream)['uri'] ?? null) === $this->secretPath) {
                stream_filter_append($stream, 'sent.reject-writes', STREAM_FILTER_WRITE);
            }
        }

        return fakeWebhookResponse();
    });
    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:setup-webhook', ['url' => 'https://example.com/webhook', '--secret-file' => $this->secretPath])
        ->expectsOutputToContain('Rotate it in the Sent.dm dashboard')
        ->doesntExpectOutputToContain('whsec_abc123')
        ->assertFailed();

    expect(file_exists($this->secretPath))->toBeFalse();
});
