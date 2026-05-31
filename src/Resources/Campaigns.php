<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Client;
use SentDm\Profiles\Campaigns\APIResponseOfTcrCampaignWithUseCases;
use SentDm\Profiles\Campaigns\CampaignData;
use SentDm\Profiles\Campaigns\CampaignDeleteParams\Body;
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
        // Pass sandbox: false explicitly — the SDK's CampaignsRawService::delete()
        // calls array_diff_key() on the body, which fails for empty bodies because
        // ModelOf::dump([]) returns stdClass. A non-empty body stays as a plain array.
        $this->client->profiles->campaigns->delete(
            campaignID: $campaignId,
            profileID: $this->profileId,
            body: Body::with(sandbox: false),
        );
    }
}
