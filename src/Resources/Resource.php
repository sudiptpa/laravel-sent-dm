<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Client;

abstract class Resource
{
    public function __construct(
        protected readonly Client $client,
        protected readonly ?CacheRepository $cache = null,
        protected readonly bool $cacheEnabled = false,
        protected readonly int $cacheTtl = 3600,
    ) {}

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
