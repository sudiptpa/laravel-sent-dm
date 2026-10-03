<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Webhooks\WebhookRotateSecretResponse;
use SentDm\Webhooks\WebhookRotateSecretResponse\Data as WebhookSecretData;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

beforeEach(function () {
    $this->secretPath = sys_get_temp_dir().'/sent-webhook-rotate-'.bin2hex(random_bytes(8)).'/webhook.env';
});

afterEach(function () {
    if (is_file($this->secretPath)) {
        unlink($this->secretPath);
    }
    if (is_dir(dirname($this->secretPath))) {
        rmdir(dirname($this->secretPath));
    }
});

function fakeRotateWebhookSecretResponse(?string $secret = 'whsec_rotated'): WebhookRotateSecretResponse
{
    $data = new WebhookSecretData;
    if ($secret !== null) {
        $data['signingSecret'] = $secret;
    }

    $response = new WebhookRotateSecretResponse;
    $response['data'] = $data;

    return $response;
}

it('rotates a webhook secret and saves it without printing it', function () {
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('rotateSecret')
        ->once()
        ->with('wh_123', true, 'idem_1')
        ->andReturn(fakeRotateWebhookSecretResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:rotate-secret', [
        'id' => 'wh_123',
        '--secret-file' => $this->secretPath,
        '--idempotency-key' => 'idem_1',
        '--sandbox' => true,
    ])
        ->expectsOutputToContain('Signing secret saved')
        ->doesntExpectOutputToContain('whsec_rotated')
        ->assertExitCode(0);

    expect(file_get_contents($this->secretPath))->toBe('SENT_WEBHOOK_SECRET="whsec_rotated"'.PHP_EOL)
        ->and(fileperms($this->secretPath) & 0777)->toBe(0600);
});

it('fails when the rotated secret response has no secret', function () {
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('rotateSecret')->once()->andReturn(fakeRotateWebhookSecretResponse(null));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:rotate-secret', ['id' => 'wh_123', '--secret-file' => $this->secretPath])
        ->expectsOutputToContain('API returned no signing secret')
        ->assertExitCode(1);

    expect(file_exists($this->secretPath))->toBeFalse();
});

it('refuses an existing rotated secret file before calling the API', function () {
    mkdir(dirname($this->secretPath), 0700, true);
    file_put_contents($this->secretPath, 'existing environment');
    $manager = Mockery::mock(SentManager::class);
    $manager->shouldNotReceive('connection');
    app()->instance(SentManager::class, $manager);

    $this->artisan('sent:webhook:rotate-secret', ['id' => 'wh_123', '--secret-file' => $this->secretPath])
        ->assertFailed();

    expect(file_get_contents($this->secretPath))->toBe('existing environment');
});

it('refuses stream wrappers for rotated secret destination', function () {
    $this->artisan('sent:webhook:rotate-secret', ['id' => 'wh_123', '--secret-file' => 'php://stdout'])
        ->assertFailed();
});

it('uses a private file under application storage by default', function () {
    app()->useStoragePath(dirname($this->secretPath));
    $this->secretPath = storage_path('app/private/sent-webhook.env');

    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('rotateSecret')->once()->andReturn(fakeRotateWebhookSecretResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:rotate-secret', ['id' => 'wh_123'])
        ->assertSuccessful();

    expect(is_file($this->secretPath))->toBeTrue()
        ->and(fileperms($this->secretPath) & 0777)->toBe(0600);
});

it('fails before rotating if the rotated secret directory cannot be created', function () {
    mkdir(dirname($this->secretPath), 0700, true);
    file_put_contents($this->secretPath, 'not a directory');
    $manager = Mockery::mock(SentManager::class);
    $manager->shouldNotReceive('connection');
    app()->instance(SentManager::class, $manager);

    $this->artisan('sent:webhook:rotate-secret', [
        'id' => 'wh_123',
        '--secret-file' => $this->secretPath.'/nested/secret.env',
    ])->assertFailed();
});

it('reports failed rotated secret writes and removes the incomplete file', function () {
    stream_filter_register('sent.reject-rotated-writes', RejectRotatedSecretWrites::class);
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Webhooks::class);

    $driver->shouldReceive('webhooks')->once()->andReturn($resource);
    $resource->shouldReceive('rotateSecret')->once()->andReturnUsing(function () {
        foreach (get_resources('stream') as $stream) {
            if ((stream_get_meta_data($stream)['uri'] ?? null) === $this->secretPath) {
                stream_filter_append($stream, 'sent.reject-rotated-writes', STREAM_FILTER_WRITE);
            }
        }

        return fakeRotateWebhookSecretResponse();
    });

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:rotate-secret', ['id' => 'wh_123', '--secret-file' => $this->secretPath])
        ->expectsOutputToContain('could not be saved')
        ->doesntExpectOutputToContain('whsec_rotated')
        ->assertFailed();

    expect(file_exists($this->secretPath))->toBeFalse();
});

it('shows failure when rotate secret API call fails', function () {
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
    $resource->shouldReceive('rotateSecret')->once()->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:webhook:rotate-secret', ['id' => 'wh_123', '--secret-file' => $this->secretPath])
        ->assertExitCode(1);

    expect(file_exists($this->secretPath))->toBeFalse();
});

class RejectRotatedSecretWrites extends php_user_filter
{
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        return PSFS_ERR_FATAL;
    }
}
