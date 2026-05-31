<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use SentDm\Client;
use SentDm\Contacts\APIResponseOfContact;

class ContactBuilder
{
    private ?string $phone = null;

    private ?string $defaultChannel = null;

    private ?bool $optOut = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?CacheRepository $cache,
        private readonly bool $cacheEnabled,
        private readonly ?string $id = null,
    ) {}

    public function phone(string $phone): static
    {
        $clone = clone $this;
        $clone->phone = $phone;

        return $clone;
    }

    public function defaultChannel(string $channel): static
    {
        $clone = clone $this;
        $clone->defaultChannel = $channel;

        return $clone;
    }

    public function optOut(bool $optOut = true): static
    {
        $clone = clone $this;
        $clone->optOut = $optOut;

        return $clone;
    }

    public function save(): APIResponseOfContact
    {
        if ($this->id !== null) {
            $result = $this->client->contacts->update(
                id: $this->id,
                defaultChannel: $this->defaultChannel,
                optOut: $this->optOut,
            );

            $this->forget("sent.contact.{$this->id}");

            return $result;
        }

        if ($this->phone === null) {
            throw new InvalidArgumentException('A phone number is required to create a contact. Call phone() before save().');
        }

        return $this->client->contacts->create(phoneNumber: $this->phone);
    }

    private function forget(string $key): void
    {
        if ($this->cacheEnabled && $this->cache !== null) {
            $this->cacheStore()->forget($key);
        }
    }

    private function cacheStore(): CacheRepository
    {
        if ($this->cache !== null && $this->cache->getStore() instanceof TaggableStore) {
            return $this->cache->tags(['sent']);
        }

        return $this->cache ?? app('cache.store');
    }
}
