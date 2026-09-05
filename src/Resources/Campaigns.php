<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Client;
use SentDm\Profiles\Campaigns\CampaignListResponse;
use SentDm\Profiles\Campaigns\CampaignNewResponse;
use SentDm\Profiles\Campaigns\CampaignUpdateResponse;

/**
 * The SDK dropped the unified `CampaignData` type in v0.29.0. `create()` and `update()`
 * each get their own nested params class now (`CampaignCreateParams\Campaign` vs
 * `CampaignUpdateParams\Campaign`), and `Campaign\UseCase` is namespaced separately per
 * variant even though the fields inside it (and the shared `MessagingUseCaseUs` enum) are
 * identical. `CampaignShape` is written out locally with `useCases: list<UseCaseShape>`
 * instead of importing either variant's `Campaign\UseCase` class, so it type-checks against
 * both create() and update(). See https://docs.sent.dm/reference/api for the full fields.
 *
 * @phpstan-type UseCaseShape = array{
 *   messagingUseCaseUs: 'ACCOUNT_NOTIFICATION'|'CUSTOMER_CARE'|'DELIVERY_NOTIFICATION'|'FRAUD_ALERT'|'HIGHER_EDUCATION'|'LOW_VOLUME'|'M2M'|'MARKETING'|'MIXED'|'POLLING_VOTING'|'PUBLIC_SERVICE_ANNOUNCEMENT'|'SECURITY_ALERT'|'TWO_FA',
 *   sampleMessages: list<string>,
 * }
 * @phpstan-type CampaignShape = array{
 *   description: string,
 *   name: string,
 *   type: string,
 *   useCases: list<UseCaseShape>,
 *   helpKeywords?: string|null,
 *   helpMessage?: string|null,
 *   messageFlow?: string|null,
 *   optinKeywords?: string|null,
 *   optinMessage?: string|null,
 *   optoutKeywords?: string|null,
 *   optoutMessage?: string|null,
 *   privacyPolicyLink?: string|null,
 *   termsAndConditionsLink?: string|null,
 *   volume?: string|null,
 * }
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
    ) {
        parent::__construct($client, $cache, $cacheEnabled, $cacheTtl);
    }

    public function get(): CampaignListResponse
    {
        return $this->client->profiles->campaigns->list(profileID: $this->profileId);
    }

    /**
     * @param  CampaignShape  $campaign
     */
    public function create(array $campaign): CampaignNewResponse
    {
        return $this->client->profiles->campaigns->create(
            profileID: $this->profileId,
            campaign: $campaign,
        );
    }

    /**
     * @param  CampaignShape  $campaign
     */
    public function update(string $campaignId, array $campaign): CampaignUpdateResponse
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
