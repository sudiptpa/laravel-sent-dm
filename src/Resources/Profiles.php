<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Profiles\APIResponseOfProfileDetail;
use SentDm\Profiles\ProfileListResponse;
use Sujip\SentDm\Builders\ProfileBuilder;
use Sujip\SentDm\Support\Sandbox;

/**
 * @deprecated Sent.dm deprecated the `profiles` sub-service in its August 2026 platform
 * changelog. `Sent::senderProfiles()` is the supported replacement. Still fully
 * functional, no removal version set.
 */
class Profiles extends Resource
{
    public function get(): ProfileListResponse
    {
        return $this->cached(
            'sent.profiles.all',
            fn () => $this->client->profiles->list(xProfileID: $this->orgProfileId),
        );
    }

    public function find(string $id): APIResponseOfProfileDetail
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
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function campaigns(string $profileId): Campaigns
    {
        $campaigns = new Campaigns($this->client, $profileId, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);

        return $this->orgProfileId !== null ? $campaigns->profile($this->orgProfileId) : $campaigns;
    }

    public function delete(string $id, ?bool $sandbox = null): void
    {
        $this->client->profiles->delete(
            profileID: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
        $this->forget('sent.profiles.all');
    }
}
