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

// Contacts -------------------------------------------------------------------

it('contacts()->find() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1', 'phone_number' => '+61412345678']);

    $sent->contacts()->find('c-1');
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(1);
});

it('contacts()->update()->save() invalidates contact cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1']);

    $sent->contacts()->find('c-1');
    $sent->contacts()->update('c-1')->defaultChannel('sms')->save();
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(3);
});

it('contacts()->delete() invalidates contact cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1']);

    $sent->contacts()->find('c-1');
    $sent->contacts()->delete('c-1');
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(3);
});

// Templates ------------------------------------------------------------------

it('templates()->find() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp']);

    $sent->templates()->find('tpl-1');
    $sent->templates()->find('tpl-1');

    expect($counter->value)->toBe(1);
});

it('templates()->findByName() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['templates' => [['id' => 'tpl-1', 'name' => 'otp']]]);

    $sent->templates()->findByName('otp');
    $sent->templates()->findByName('otp');

    expect($counter->value)->toBe(1);
});

it('templates()->get() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['templates' => []]);

    $sent->templates()->get();
    $sent->templates()->get();

    expect($counter->value)->toBe(1);
});

it('templates()->delete() invalidates template cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1']);

    $sent->templates()->find('tpl-1');
    $sent->templates()->delete('tpl-1');
    $sent->templates()->find('tpl-1');

    expect($counter->value)->toBe(3);
});

it('templates()->update()->save() also invalidates findByName cache when find was cached', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp']);

    // populate the find cache with a named template
    $sent->templates()->find('tpl-1');

    // update — should evict both the find cache and the findByName('otp') slot
    $sent->templates()->update('tpl-1')->name('otp-v2')->save();

    // findByName must hit the API (not serve stale cache)
    $sent->templates()->findByName('otp');

    // 3 calls: find + update + findByName (no cache hit on findByName)
    expect($counter->value)->toBe(3);
});

it('templates()->delete() also invalidates findByName cache when find was cached', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp']);

    // populate the find cache with a named template
    $sent->templates()->find('tpl-1');

    // delete — should evict both the find cache and the findByName('otp') slot
    $sent->templates()->delete('tpl-1');

    // findByName must hit the API (not serve stale cache)
    $sent->templates()->findByName('otp');

    // 3 calls: find + delete + findByName (no cache hit on findByName)
    expect($counter->value)->toBe(3);
});

// Profiles -------------------------------------------------------------------

it('profiles()->get() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['profiles' => []]);

    $sent->profiles()->get();
    $sent->profiles()->get();

    expect($counter->value)->toBe(1);
});

it('profiles()->create()->save() invalidates profiles cache', function () {
    [$sent, $counter] = sentWithCache(['profiles' => []]);

    $sent->profiles()->get();
    $sent->profiles()->create()->name('New Profile')->save();
    $sent->profiles()->get();

    expect($counter->value)->toBe(3);
});

it('profiles()->delete() invalidates profiles cache', function () {
    [$sent, $counter] = sentWithCache(['profiles' => []]);

    $sent->profiles()->get();
    $sent->profiles()->delete('prof-1');
    $sent->profiles()->get();

    expect($counter->value)->toBe(3);
});

// Number lookup --------------------------------------------------------------

it('lookup() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['isValid' => true, 'carrierName' => 'Telstra']);

    $sent->lookup('+61412345678');
    $sent->lookup('+61412345678');

    expect($counter->value)->toBe(1);
});

// Cache disabled -------------------------------------------------------------

it('bypasses cache when cacheEnabled is false', function () {
    $bypassCounter = new class
    {
        public int $value = 0;
    };

    $opts = new RequestOptions;
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

    $sent->contacts()->find('c-1');
    $sent->contacts()->find('c-1');

    expect($bypassCounter->value)->toBe(2);
});

it('ContactBuilder::update() invalidates cache via non-tagged store', function () {
    $fileStore = new FileStore(new Filesystem, sys_get_temp_dir().'/sent-cache-builder-'.uniqid());
    $cache = new Repository($fileStore);

    $counter = new class
    {
        public int $value = 0;
    };

    $opts = new RequestOptions;
    $opts['transporter'] = new class($counter) implements ClientInterface
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

    $sent = new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
    );

    $sent->contacts()->find('c-1');
    $sent->contacts()->update('c-1')->defaultChannel('sms')->save();
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(3);
});

it('uses non-tagged cache store directly when store does not support tags', function () {
    $fileStore = new FileStore(new Filesystem, sys_get_temp_dir().'/sent-cache-test-'.uniqid());
    $cache = new Repository($fileStore);

    $counter = new class
    {
        public int $value = 0;
    };

    $opts = new RequestOptions;
    $opts['transporter'] = new class($counter) implements ClientInterface
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

    $sent = new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
    );

    $sent->contacts()->find('c-1');
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(1);
});
