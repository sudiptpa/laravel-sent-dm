<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Client;
use SentDm\Profiles\Campaigns\APIResponseOfTcrCampaignWithUseCases;
use SentDm\Profiles\Campaigns\CampaignData;
use SentDm\Profiles\Campaigns\CampaignListResponse;

/**
 * @phpstan-import-type CampaignDataShape from CampaignData
 */
class Campaigns extends Resource
{
    public function __construct(
        Client $client,
        private readonly string $profileId,
        ?CacheRepository $cache = null,
        bool $cacheEnabled = false,
        int $cacheTtl = 3600,
    ) {
        parent::__construct($client, $cache, $cacheEnabled, $cacheTtl);
    }

    public function get(): CampaignListResponse
    {
        return $this->client->profiles->campaigns->list(profileID: $this->profileId);
    }

    /**
     * @param  CampaignData|CampaignDataShape  $campaign
     */
    public function create(CampaignData|array $campaign): APIResponseOfTcrCampaignWithUseCases
    {
        return $this->client->profiles->campaigns->create(
            profileID: $this->profileId,
            campaign: $campaign,
        );
    }

    /**
     * @param  CampaignData|CampaignDataShape  $campaign
     */
    public function update(string $campaignId, CampaignData|array $campaign): APIResponseOfTcrCampaignWithUseCases
    {
        return $this->client->profiles->campaigns->update(
            campaignID: $campaignId,
            profileID: $this->profileId,
            campaign: $campaign,
        );
    }

    public function delete(string $campaignId): void
    {
        $this->client->profiles->campaigns->delete(
            campaignID: $campaignId,
            profileID: $this->profileId,
        );
    }
}
