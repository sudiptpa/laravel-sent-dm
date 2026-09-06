<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Client;

abstract class Resource
{
    /**
     * Org-key scoping: which child profile to run the request as. Set via profile(), sent
     * as the `x-profile-id` header. Named to not collide with a resource's own $profileId
     * (e.g. Campaigns, which is a required path segment, a different thing entirely).
     */
    protected ?string $orgProfileId = null;

    public function __construct(
        protected readonly Client $client,
        protected readonly ?CacheRepository $cache = null,
        protected readonly bool $cacheEnabled = false,
        protected readonly int $cacheTtl = 3600,
        protected readonly bool $sandbox = false,
    ) {}

    /**
     * Scope every call made through this instance to one child profile, via the
     * `x-profile-id` header. Every v3 operation accepts it except `/v3/sender-profiles`
     * itself, calling profile() there has no effect since that resource has nothing to
     * scope into. Works with a standard API key, not only an organization-tier one.
     */
    public function profile(string $id): static
    {
        $clone = clone $this;
        $clone->orgProfileId = $id;

        return $clone;
    }

    /**
     * Call an endpoint the SDK doesn't have a typed method for yet, via the SDK's own
     * generic `Client::request()` (inherited from `BaseClient`, auth injected
     * automatically by `Client::buildRequest()`). Same transport, same auth, same
     * retries as every typed SDK call, just no generated request/response classes to
     * lean on (see `CONTRIBUTING.md`).
     *
     * `convert: 'mixed'` is required, not optional: the SDK's own default is `'null'`,
     * which silently discards a non-null response and returns null instead of the
     * decoded body. Passing 'mixed' is what makes `parse()` return the real payload.
     *
     * `unwrap: 'data'` matches every typed SDK response, which exposes the same thing as
     * `->data`. Every v3 endpoint wraps its payload in the same `{success, data, error,
     * meta}` envelope, so returning the envelope itself here instead of unwrapping it
     * would make this the only resource in the package where callers have to reach
     * through `['data']` to get anything.
     *
     * Returns the raw decoded array. Callers hydrate it into their own typed response
     * class (see `src/Responses/`), the same way every other resource in this package
     * returns a typed object, not a bare array.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function raw(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $headers = [],
    ): array {
        if ($this->orgProfileId !== null) {
            $headers += ['x-profile-id' => $this->orgProfileId];
        }

        $result = $this->client->request(
            method: $method,
            path: $path,
            query: $query,
            headers: $headers,
            body: $body,
            unwrap: 'data',
            convert: 'mixed',
        )->parse();

        // A DELETE with no response body parses to null at runtime, confirmed directly,
        // even though the SDK's own stub for convert: 'mixed' claims otherwise.
        $result ??= [];

        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function cached(string $key, Closure $callback): mixed
    {
        if (! $this->cacheEnabled || $this->cache === null) {
            return $callback();
        }

        return $this->cacheStore()->remember($key, $this->cacheTtl, $callback);
    }

    protected function forget(string $key): void
    {
        if ($this->cacheEnabled && $this->cache !== null) {
            $this->cacheStore()->forget($key);
        }
    }

    protected function readCached(string $key): mixed
    {
        if (! $this->cacheEnabled || $this->cache === null) {
            return null;
        }

        return $this->cacheStore()->get($key);
    }

    private function cacheStore(): CacheRepository
    {
        if ($this->cache !== null && $this->cache->getStore() instanceof TaggableStore) {
            return $this->cache->tags(['sent']);
        }

        return $this->cache ?? app('cache.store');
    }
}
