<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Sent;

/**
 * Build a Sent driver with an in-process array cache and a fake HTTP transporter.
 * The transporter is a counter: we can assert how many real SDK calls were made.
 *
 * @param  array<string, mixed>  $data
 */
function sentWithCache(array $data = []): array
{
    $counter = new class
    {
        public int $value = 0;
    };

    $body = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($body, $counter) implements ClientInterface
    {
        public function __construct(private string $body, private object $counter) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->counter->value++;

            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $cache = new Repository(new ArrayStore);

    $sent = new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
    );

    return [$sent, $counter];
}

it('caches getContact and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1', 'phone_number' => '+61412345678']);

    $sent->getContact('c-1');
    $sent->getContact('c-1');

    expect($counter->value)->toBe(1);
});

it('caches getTemplate and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp']);

    $sent->getTemplate('tpl-1');
    $sent->getTemplate('tpl-1');

    expect($counter->value)->toBe(1);
});

it('caches getTemplateByName', function () {
    [$sent, $counter] = sentWithCache(['templates' => [['id' => 'tpl-1', 'name' => 'otp']]]);

    $sent->getTemplateByName('otp');
    $sent->getTemplateByName('otp');

    expect($counter->value)->toBe(1);
});

it('caches listTemplates', function () {
    [$sent, $counter] = sentWithCache(['templates' => []]);

    $sent->listTemplates();
    $sent->listTemplates();

    expect($counter->value)->toBe(1);
});

it('caches lookup', function () {
    [$sent, $counter] = sentWithCache(['isValid' => true, 'carrierName' => 'Telstra']);

    $sent->lookup('+61412345678');
    $sent->lookup('+61412345678');

    expect($counter->value)->toBe(1);
});

it('caches listProfiles', function () {
    [$sent, $counter] = sentWithCache(['profiles' => []]);

    $sent->listProfiles();
    $sent->listProfiles();

    expect($counter->value)->toBe(1);
});

it('invalidates contact cache after updateContact', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1']);

    $sent->getContact('c-1');
    $sent->updateContact('c-1', defaultChannel: 'sms');
    $sent->getContact('c-1');

    expect($counter->value)->toBe(3);
});

it('invalidates contact cache after deleteContact', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1']);

    $sent->getContact('c-1');
    $sent->deleteContact('c-1');
    $sent->getContact('c-1');

    expect($counter->value)->toBe(3);
});

it('invalidates template cache after deleteTemplate', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1']);

    $sent->getTemplate('tpl-1');
    $sent->deleteTemplate('tpl-1');
    $sent->getTemplate('tpl-1');

    expect($counter->value)->toBe(3);
});

it('invalidates profiles cache after deleteProfile', function () {
    [$sent, $counter] = sentWithCache(['profiles' => []]);

    $sent->listProfiles();
    $sent->deleteProfile('prof-1');
    $sent->listProfiles();

    expect($counter->value)->toBe(3);
});

it('bypasses cache when cacheEnabled is false', function () {
    $opts = new RequestOptions;
    $callCount = 0;
    $bypassCounter = new class
    {
        public int $value = 0;
    };
    $opts['transporter'] = new class($bypassCounter) implements ClientInterface
    {
        public function __construct(private object $counter) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->counter->value++;

            return new Response(200, ['Content-Type' => 'application/json'],
                '{"success":true,"data":{"id":"c-1"},"meta":{"request_id":"t","timestamp":"2025-01-01T00:00:00Z","version":"v3"}}');
        }
    };
    $opts['maxRetries'] = 0;

    $cache = new Repository(new ArrayStore);
    $sent = new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 3600,
    );

    $sent->getContact('c-1');
    $sent->getContact('c-1');

    expect($bypassCounter->value)->toBe(2);
});

it('uses cache store directly when store is not TaggableStore', function () {
    // FileStore does not implement TaggableStore — hits the fallback path in cacheStore()
    $fileStore = new FileStore(new Filesystem, sys_get_temp_dir().'/sent-cache-test-'.uniqid());
    $cache = new Repository($fileStore);

    $counter = new class
    {
        public int $value = 0;
    };
    $transporter = new class($counter) implements ClientInterface
    {
        public function __construct(private object $counter) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->counter->value++;

            return new Response(200, ['Content-Type' => 'application/json'],
                '{"success":true,"data":{"id":"c-1"},"meta":{"request_id":"t","timestamp":"2025-01-01T00:00:00Z","version":"v3"}}');
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
    );

    // Two calls — should still cache (non-tagged store caches by key directly)
    $sent->getContact('c-1');
    $sent->getContact('c-1');

    expect($counter->value)->toBe(1);
});
