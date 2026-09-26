<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Filesystem\Filesystem;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Resources\Resource;
use Sujip\SentDm\Sent;

/**
 * Build a Sent driver with an in-process array cache and a test HTTP transport.
 * The transport is a counter so tests can assert how many SDK calls were made.
 *
 * @param  array<string, mixed>  $data
 */
function sentWithCache(array $data = [], ?Repository $cache = null, string $connection = 'default', string $credential = 'test'): array
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

    $cache ??= new Repository(new ArrayStore);

    $sent = new Sent(
        client: new Client(apiKey: $credential, requestOptions: $opts),
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        connectionName: $connection,
    );

    return [$sent, $counter];
}

it('isolates cached results between connections sharing a cache store', function () {
    $cache = new Repository(new ArrayStore);
    [$first, $firstCalls] = sentWithCache(['id' => 'shared', 'phone_number' => '+14155550101'], $cache, 'first');
    [$second, $secondCalls] = sentWithCache(['id' => 'shared', 'phone_number' => '+14155550102'], $cache, 'second');

    $first->contacts()->find('shared');
    $second->contacts()->find('shared');
    $first->contacts()->find('shared');
    $second->contacts()->find('shared');

    expect($firstCalls->value)->toBe(1)->and($secondCalls->value)->toBe(1);
});

it('isolates cached results when the credentials change', function () {
    $cache = new Repository(new ArrayStore);
    [$first, $firstCalls] = sentWithCache(['id' => 'shared'], $cache, credential: 'first');
    [$second, $secondCalls] = sentWithCache(['id' => 'shared'], $cache, credential: 'second');

    $first->contacts()->find('shared');
    $second->contacts()->find('shared');

    expect($firstCalls->value)->toBe(1)->and($secondCalls->value)->toBe(1);
});

it('isolates reads and invalidation between child profiles', function () {
    [$sent, $counter] = sentWithCache(['id' => 'shared']);
    $first = $sent->contacts()->profile('first');
    $second = $sent->contacts()->profile('second');

    $first->find('shared');
    $second->find('shared');
    $first->update('shared')->defaultChannel('sms')->save();
    $second->find('shared');
    $first->find('shared');

    expect($counter->value)->toBe(4);
});

it('does not cache template list pages with a serializing cache', function () {
    [$sent, $counter] = sentWithCache([
        'templates' => [['id' => 'template-1', 'name' => 'welcome']],
        'pagination' => ['has_more' => false],
    ], new Repository(new ArrayStore(true)));

    $sent->templates()->get();
    $page = $sent->templates()->get();

    expect($page->hasNextPage())->toBeFalse()
        ->and($page->getItems())->toHaveCount(1)
        ->and($counter->value)->toBe(2);
});

it('refreshes name lookups after writes without first retrieving the template', function () {
    [$sent, $counter] = sentWithCache(['templates' => [['id' => 'tpl-1', 'name' => 'otp']]]);

    $sent->templates()->findByName('otp');
    $sent->templates()->update('tpl-1')->name('changed')->save();
    $sent->templates()->findByName('otp');

    expect($counter->value)->toBe(3);
});

// Contacts -------------------------------------------------------------------

it('contacts()->find() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1', 'phone_number' => '+61412345678']);

    $sent->contacts()->find('c-1');
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(1);
});

it('recomputes instead of returning a cache entry with an incomplete class', function () {
    $corrupt = ['items' => [(object) ['value' => unserialize('O:19:"NonExistentClassXYZ":0:{}')]]];
    $store = new class($corrupt) implements Store
    {
        public function __construct(private mixed $corruptValue) {}

        public function get($key)
        {
            return $this->corruptValue;
        }

        public function many(array $keys)
        {
            return array_fill_keys($keys, null);
        }

        public function put($key, $value, $seconds)
        {
            return true;
        }

        public function putMany(array $values, $seconds)
        {
            return true;
        }

        public function touch($key, $seconds)
        {
            return true;
        }

        public function increment($key, $value = 1)
        {
            return false;
        }

        public function decrement($key, $value = 1)
        {
            return false;
        }

        public function forever($key, $value)
        {
            return true;
        }

        public function forget($key)
        {
            return true;
        }

        public function flush()
        {
            return true;
        }

        public function getPrefix()
        {
            return '';
        }
    };

    [$sent, $counter] = sentWithCache(['id' => 'c-1'], new Repository($store));

    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(1);
});

it('serves cached values with circular public references', function () {
    $cached = new stdClass;
    $cached->id = 'cached';
    $cached->self = $cached;

    $store = new class($cached) implements Store
    {
        public function __construct(private mixed $cachedValue) {}

        public function get($key)
        {
            return $this->cachedValue;
        }

        public function many(array $keys)
        {
            return array_fill_keys($keys, null);
        }

        public function put($key, $value, $seconds)
        {
            return true;
        }

        public function putMany(array $values, $seconds)
        {
            return true;
        }

        public function touch($key, $seconds)
        {
            return true;
        }

        public function increment($key, $value = 1)
        {
            return false;
        }

        public function decrement($key, $value = 1)
        {
            return false;
        }

        public function forever($key, $value)
        {
            return true;
        }

        public function forget($key)
        {
            return true;
        }

        public function flush()
        {
            return true;
        }

        public function getPrefix()
        {
            return '';
        }
    };

    $resource = new class(new Client(apiKey: 'test'), new Repository($store), true) extends Resource
    {
        public function value(): mixed
        {
            return $this->cached('loop', fn () => 'fresh');
        }
    };

    expect($resource->value()->id)->toBe('cached');
});

it('contacts()->update()->save() invalidates contact cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1']);

    $sent->contacts()->find('c-1');
    $sent->contacts()->update('c-1')->defaultChannel('sms')->save();
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(3);
});

it('senderProfiles()->update()->save() invalidates the sender profile cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'sp-1', 'name' => 'Test', 'short_name' => 'TST']);

    $sent->senderProfiles()->find('sp-1');
    $sent->senderProfiles()->update('sp-1')->name('Renamed')->save();
    $sent->senderProfiles()->find('sp-1');

    expect($counter->value)->toBe(3);
});

it('contacts()->delete() invalidates contact cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'c-1']);

    $sent->contacts()->find('c-1');
    $sent->contacts()->delete('c-1');
    $sent->contacts()->find('c-1');

    expect($counter->value)->toBe(3);
});

it('contacts()->messageSummary() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['contact_id' => 'c-1', 'message_count' => 12]);

    $sent->contacts()->messageSummary('c-1');
    $sent->contacts()->messageSummary('c-1');

    expect($counter->value)->toBe(1);
});

it('contacts()->delete() invalidates message summary cache', function () {
    [$sent, $counter] = sentWithCache(['contact_id' => 'c-1', 'message_count' => 12]);

    $sent->contacts()->messageSummary('c-1');
    $sent->contacts()->delete('c-1');
    $sent->contacts()->messageSummary('c-1');

    expect($counter->value)->toBe(3);
});

// Templates ------------------------------------------------------------------

it('templates()->find() caches and serves from cache on second call', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp', 'templates' => [['id' => 'tpl-1', 'name' => 'otp']]]);

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

it('templates()->get() calls the API on each request', function () {
    [$sent, $counter] = sentWithCache(['templates' => []]);

    $sent->templates()->get();
    $sent->templates()->get();

    expect($counter->value)->toBe(2);
});

it('templates()->delete() invalidates template cache', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1']);

    $sent->templates()->find('tpl-1');
    $sent->templates()->delete('tpl-1');
    $sent->templates()->find('tpl-1');

    expect($counter->value)->toBe(3);
});

it('templates()->update()->save() also invalidates findByName cache when find was cached', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp', 'templates' => [['id' => 'tpl-1', 'name' => 'otp']]]);

    // populate the find cache with a named template
    $sent->templates()->find('tpl-1');

    // update: should evict both the find cache and the findByName('otp') slot
    $sent->templates()->update('tpl-1')->name('otp-v2')->save();

    // findByName must hit the API (not serve stale cache)
    $sent->templates()->findByName('otp');

    // 3 calls: find + update + findByName (no cache hit on findByName)
    expect($counter->value)->toBe(3);
});

it('templates()->delete() also invalidates findByName cache when find was cached', function () {
    [$sent, $counter] = sentWithCache(['id' => 'tpl-1', 'name' => 'otp', 'templates' => [['id' => 'tpl-1', 'name' => 'otp']]]);

    // populate the find cache with a named template
    $sent->templates()->find('tpl-1');

    // delete: should evict both the find cache and the findByName('otp') slot
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
