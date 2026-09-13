<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Client;
use SentDm\Profiles\Campaigns\APIResponseOfBrandCampaign;
use SentDm\Profiles\Campaigns\APIResponseOfListOfBrandCampaign;
use SentDm\Profiles\Campaigns\CampaignData;

/**
 * @deprecated Sent.dm deprecated the entire `campaigns` sub-service in its August 2026
 * platform changelog. A campaign is now registered per market via `compliance.campaign`
 * on the channel-add call instead of as its own resource. Still fully functional, no
 * removal version set.
 *
 * v0.31.0 re-unified `CampaignData` into one shape shared by create and update again,
 * after v0.29.0 had split it into separate per-operation param shapes.
 *
 * @phpstan-import-type CampaignDataShape from CampaignData
 *
 * `volume` (a numeric string, e.g. `"1500"`) has a silent-cost gotcha per Sent.dm's
 * August 2026 platform changelog: omitting it does not error, it registers the campaign
 * at the standard tier, the higher monthly fee, with nothing surfaced to flag it. Values
 * strictly below `2000` register low-volume instead (capped at 2,000 messages/day, lower
 * fee). Set it explicitly on every low-volume campaign.
 */
class Campaigns extends Resource
{
    public function __construct(
        Client $client,
        private readonly string $profileId,
        ?CacheRepository $cache = null,
        bool $cacheEnabled = false,
        int $cacheTtl = 3600,
        bool $sandbox = false,
    ) {
        parent::__construct($client, $cache, $cacheEnabled, $cacheTtl, $sandbox);
    }

    public function get(): APIResponseOfListOfBrandCampaign
    {
        return $this->client->profiles->campaigns->list(
            profileID: $this->profileId,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * Best practice per Sent.dm: set `volume` explicitly whenever this campaign should
     * register as low-volume. Leaving it out is not an error, it registers as standard
     * (the higher-fee tier) with nothing in the response to flag it.
     *
     * @param  CampaignDataShape  $campaign
     */
    public function create(array $campaign, ?string $idempotencyKey = null, ?bool $sandbox = null): APIResponseOfBrandCampaign
    {
        return $this->client->profiles->campaigns->create(
            profileID: $this->profileId,
            campaign: $campaign,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * Best practice per Sent.dm: set `volume` explicitly whenever this campaign should
     * register as low-volume. Leaving it out is not an error, it registers as standard
     * (the higher-fee tier) with nothing in the response to flag it.
     *
     * @param  CampaignDataShape  $campaign
     */
    public function update(string $campaignId, array $campaign, ?string $idempotencyKey = null, ?bool $sandbox = null): APIResponseOfBrandCampaign
    {
        return $this->client->profiles->campaigns->update(
            campaignID: $campaignId,
            profileID: $this->profileId,
            campaign: $campaign,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function delete(string $campaignId, ?bool $sandbox = null): void
    {
        $this->client->profiles->campaigns->delete(
            campaignID: $campaignId,
            profileID: $this->profileId,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            xProfileID: $this->orgProfileId,
        );
    }
}
