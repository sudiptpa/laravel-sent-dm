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
            fn () => $this->client->profiles->list(xProfileID: $this->orgProfileId),
        );
    }

    public function find(string $id): ProfileGetResponse
    {
        return $this->client->profiles->retrieve(profileID: $id, xProfileID: $this->orgProfileId);
    }

    public function create(): ProfileBuilder
    {
        return new ProfileBuilder(
            client: $this->client,
            profileId: $this->orgProfileId,
            onSaved: fn () => $this->forget('sent.profiles.all'),
            sandboxDefault: $this->sandbox,
        );
    }

    public function update(string $id): ProfileBuilder
    {
        return new ProfileBuilder(
            client: $this->client,
            id: $id,
            profileId: $this->orgProfileId,
            onSaved: fn () => $this->forget('sent.profiles.all'),
            sandboxDefault: $this->sandbox,
        );
    }

    public function complete(string $profileId, string $webHookUrl, ?string $idempotencyKey = null, ?bool $sandbox = null): mixed
    {
        return $this->client->profiles->complete(
            profileID: $profileId,
            webHookURL: $webHookUrl,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function campaigns(string $profileId): Campaigns
    {
        $campaigns = new Campaigns($this->client, $profileId, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox);

        return $this->orgProfileId !== null ? $campaigns->profile($this->orgProfileId) : $campaigns;
    }

    public function delete(string $id, ?bool $sandbox = null): void
    {
        $this->client->profiles->delete(
            profileID: $id,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            xProfileID: $this->orgProfileId,
        );
        $this->forget('sent.profiles.all');
    }
}
