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
        protected readonly string $connectionName = 'default',
    ) {}

    /**
     * Scope every call made through this instance to one child profile, via the
     * `x-profile-id` header. Every v3 operation accepts it except `/v3/sender-profiles`
     * itself, calling profile() there has no effect since that resource has nothing to
     * scope into. Child-profile scoping requires an organization API key.
     */
    public function profile(string $id): static
    {
        $clone = clone $this;
        $clone->orgProfileId = $id;

        return $clone;
    }

    /**
     * For requests the typed SDK methods cannot send: a document upload, or an update that
     * clears a value with null. Returns the payload from `data`, so wrap it into the SDK's
     * model with `Conversion::coerce()`. `convert: 'mixed'` is needed, or the SDK drops the body.
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

        // A DELETE with no response body parses to null at runtime, even though the
        // SDK's own stub for convert: 'mixed' claims otherwise.
        $result ??= [];

        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * Request options for typed calls that take no `xProfileID` parameter.
     *
     * @return array{extraHeaders: array<string, string>}|null
     */
    protected function profileOptions(): ?array
    {
        return $this->orgProfileId !== null
            ? ['extraHeaders' => ['x-profile-id' => $this->orgProfileId]]
            : null;
    }

    /**
     * `Idempotency-Key` is a header, so a `raw()` call sends it here, not in the body.
     *
     * @return array<string, string>
     */
    protected function idempotencyHeader(?string $idempotencyKey): array
    {
        return $idempotencyKey !== null ? ['Idempotency-Key' => $idempotencyKey] : [];
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

        $store = $this->cacheStore();
        $cacheKey = $this->cacheKey($key);
        $value = $store->get($cacheKey);

        if ($value !== null && ! $this->containsIncompleteClass($value)) {
            return $value;
        }

        $fresh = $callback();
        $store->put($cacheKey, $fresh, $this->cacheTtl);

        return $fresh;
    }

    /**
     * Reject cache values that contain objects PHP could not restore.
     *
     * @param  array<int, true>|null  $seen
     */
    private function containsIncompleteClass(mixed $value, ?array $seen = null, int $depth = 0): bool
    {
        if ($depth > 20) {
            return false;
        }

        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsIncompleteClass($item, $seen, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_object($value)) {
            return false;
        }

        $seen ??= [];
        $objectId = spl_object_id($value);
        if (isset($seen[$objectId])) {
            return false;
        }

        $seen[$objectId] = true;

        foreach (get_object_vars($value) as $property) {
            if ($this->containsIncompleteClass($property, $seen, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    protected function forget(string $key): void
    {
        if ($this->cacheEnabled && $this->cache !== null) {
            $this->cacheStore()->forget($this->cacheKey($key));
        }
    }

    protected function readCached(string $key): mixed
    {
        if (! $this->cacheEnabled || $this->cache === null) {
            return null;
        }

        $value = $this->cacheStore()->get($this->cacheKey($key));

        return $this->containsIncompleteClass($value) ? null : $value;
    }

    private function cacheKey(string $key): string
    {
        $scope = hash('sha256', serialize([
            $this->connectionName,
            $this->client->apiKey,
            $this->orgProfileId,
        ]));

        return "sent.v2.{$scope}.{$key}";
    }

    private function cacheStore(): CacheRepository
    {
        if ($this->cache !== null && $this->cache->getStore() instanceof TaggableStore) {
            return $this->cache->tags(['sent']);
        }

        return $this->cache ?? app('cache.store');
    }
}
