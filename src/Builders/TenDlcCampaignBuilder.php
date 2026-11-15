<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use SentDm\Channels\SMS\APIResponseOfSMSMarket;
use SentDm\Channels\SMS\TenDlc\TenDlcCampaignUseCase;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Resources\Channels;

/**
 * Fluent alternative to `Channels::addTenDlcCampaign()` and `Channels::updateTenDlcCampaign()`.
 * With a campaign id it updates and sends only the fields set here, without one it adds a campaign.
 *
 * @phpstan-import-type TenDlcCampaignUseCaseShape from TenDlcCampaignUseCase
 */
class TenDlcCampaignBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $description = null;

    private ?string $helpKeywords = null;

    private ?string $helpMessage = null;

    private ?string $messageFlow = null;

    private ?string $optInKeywords = null;

    private ?string $optInMessage = null;

    private ?string $optOutKeywords = null;

    private ?string $optOutMessage = null;

    private ?string $privacyPolicyLink = null;

    private ?string $termsAndConditionsLink = null;

    private ?string $volume = null;

    /** @var list<TenDlcCampaignUseCase|TenDlcCampaignUseCaseShape>|null */
    private ?array $useCases = null;

    public function __construct(
        private readonly Channels $resource,
        private readonly ?string $campaignId = null,
    ) {}

    public function description(string $value): static
    {
        $clone = clone $this;
        $clone->description = $value;

        return $clone;
    }

    public function helpKeywords(string $value): static
    {
        $clone = clone $this;
        $clone->helpKeywords = $value;

        return $clone;
    }

    public function helpMessage(string $value): static
    {
        $clone = clone $this;
        $clone->helpMessage = $value;

        return $clone;
    }

    public function messageFlow(string $value): static
    {
        $clone = clone $this;
        $clone->messageFlow = $value;

        return $clone;
    }

    public function optInKeywords(string $value): static
    {
        $clone = clone $this;
        $clone->optInKeywords = $value;

        return $clone;
    }

    public function optInMessage(string $value): static
    {
        $clone = clone $this;
        $clone->optInMessage = $value;

        return $clone;
    }

    public function optOutKeywords(string $value): static
    {
        $clone = clone $this;
        $clone->optOutKeywords = $value;

        return $clone;
    }

    public function optOutMessage(string $value): static
    {
        $clone = clone $this;
        $clone->optOutMessage = $value;

        return $clone;
    }

    public function privacyPolicyLink(string $value): static
    {
        $clone = clone $this;
        $clone->privacyPolicyLink = $value;

        return $clone;
    }

    public function termsAndConditionsLink(string $value): static
    {
        $clone = clone $this;
        $clone->termsAndConditionsLink = $value;

        return $clone;
    }

    public function volume(string $value): static
    {
        $clone = clone $this;
        $clone->volume = $value;

        return $clone;
    }

    /** @param  list<TenDlcCampaignUseCase|TenDlcCampaignUseCaseShape>  $useCases */
    public function useCases(array $useCases): static
    {
        $clone = clone $this;
        $clone->useCases = $useCases;

        return $clone;
    }

    public function save(): APIResponseOfSMSMarket
    {
        $data = array_filter([
            'description' => $this->description,
            'help_keywords' => $this->helpKeywords,
            'help_message' => $this->helpMessage,
            'message_flow' => $this->messageFlow,
            'opt_in_keywords' => $this->optInKeywords,
            'opt_in_message' => $this->optInMessage,
            'opt_out_keywords' => $this->optOutKeywords,
            'opt_out_message' => $this->optOutMessage,
            'privacy_policy_link' => $this->privacyPolicyLink,
            'terms_and_conditions_link' => $this->termsAndConditionsLink,
            'volume' => $this->volume,
            'use_cases' => $this->useCases,
            'sandbox' => $this->sandbox,
        ], fn (mixed $value): bool => $value !== null);

        if ($this->campaignId !== null) {
            return $this->resource->updateTenDlcCampaign($this->campaignId, $data, $this->idempotencyKey);
        }

        return $this->resource->addTenDlcCampaign($data, $this->idempotencyKey);
    }
}
