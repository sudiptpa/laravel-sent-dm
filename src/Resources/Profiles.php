<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Profiles\ProfileGetResponse;
use SentDm\Profiles\ProfileListResponse;
use Sujip\SentDm\Builders\ProfileBuilder;

/** @deprecated */
class Profiles extends Resource
{
    public function get(): ProfileListResponse
    {
        return $this->cached(
            'sent.profiles.all',
            fn () => $this->client->profiles->list(),
        );
    }

    public function find(string $id): ProfileGetResponse
    {
        return $this->client->profiles->retrieve(profileID: $id);
    }

    public function create(): ProfileBuilder
    {
        return new ProfileBuilder(
            client: $this->client,
            onSaved: fn () => $this->forget('sent.profiles.all'),
        );
    }

    public function update(string $id): ProfileBuilder
    {
        return new ProfileBuilder(
            client: $this->client,
            id: $id,
            onSaved: fn () => $this->forget('sent.profiles.all'),
        );
    }

    public function complete(string $profileId, string $webHookUrl): mixed
    {
        return $this->client->profiles->complete(profileID: $profileId, webHookURL: $webHookUrl);
    }

    public function campaigns(string $profileId): Campaigns
    {
        return new Campaigns($this->client, $profileId, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    public function delete(string $id): void
    {
        $this->client->profiles->delete(profileID: $id);
        $this->forget('sent.profiles.all');
    }
}
