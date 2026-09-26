<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Responses\RcsAgentData;

/**
 * Fluent alternative to `Channels::addRcs(array $data)`, which stays as-is.
 * `POST /v3/channels/rcs` has no update endpoint, so this is create-only.
 *
 * The 24 required setters below match `Channels::REQUIRED_RCS_FIELDS` exactly: a
 * request missing any of them 400s live naming every field it's missing, even though
 * the live OpenAPI spec's own `required` list is empty for this endpoint. The schema
 * isn't the source of truth here, live behavior is.
 */
class RcsAgentBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $displayName = null;

    private ?string $description = null;

    private ?string $agentUseCase = null;

    private ?string $brandName = null;

    private ?string $privacyPolicyUrl = null;

    private ?string $termsAndConditionsUrl = null;

    private ?string $websiteUrl = null;

    private ?string $brandColor = null;

    private ?string $logoUrl = null;

    private ?string $bannerUrl = null;

    private ?string $brandPhoneNumber = null;

    private ?string $customerSupportPhoneNumber = null;

    private ?string $brandEmail = null;

    private ?string $customerSupportEmail = null;

    private ?string $contactNameAndTitle = null;

    private ?string $companyEin = null;

    private ?string $entityType = null;

    /** @var array<string, mixed>|null */
    private ?array $officialAddress = null;

    private ?string $briefCompanyDescription = null;

    private ?string $optInProcessDescription = null;

    private ?string $startMessage = null;

    private ?string $helpMessage = null;

    private ?string $stopMessage = null;

    /** @var list<string> */
    private array $sampleMessages = [];

    private ?string $hostingRegion = null;

    private ?string $billingCategory = null;

    private ?string $optInScreenshotUrl = null;

    public function __construct(
        private readonly Channels $resource,
    ) {}

    public function displayName(string $displayName): static
    {
        $clone = clone $this;
        $clone->displayName = $displayName;

        return $clone;
    }

    public function description(string $description): static
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    /** One of VERIFICATION, NOTIFICATIONS, MARKETING, MULTI_USE. */
    public function agentUseCase(string $agentUseCase): static
    {
        $clone = clone $this;
        $clone->agentUseCase = $agentUseCase;

        return $clone;
    }

    public function brandName(string $brandName): static
    {
        $clone = clone $this;
        $clone->brandName = $brandName;

        return $clone;
    }

    public function privacyPolicyUrl(string $url): static
    {
        $clone = clone $this;
        $clone->privacyPolicyUrl = $url;

        return $clone;
    }

    public function termsAndConditionsUrl(string $url): static
    {
        $clone = clone $this;
        $clone->termsAndConditionsUrl = $url;

        return $clone;
    }

    public function websiteUrl(string $url): static
    {
        $clone = clone $this;
        $clone->websiteUrl = $url;

        return $clone;
    }

    public function brandColor(string $brandColor): static
    {
        $clone = clone $this;
        $clone->brandColor = $brandColor;

        return $clone;
    }

    public function logoUrl(string $url): static
    {
        $clone = clone $this;
        $clone->logoUrl = $url;

        return $clone;
    }

    public function bannerUrl(string $url): static
    {
        $clone = clone $this;
        $clone->bannerUrl = $url;

        return $clone;
    }

    public function brandPhoneNumber(string $phoneNumber): static
    {
        $clone = clone $this;
        $clone->brandPhoneNumber = $phoneNumber;

        return $clone;
    }

    public function customerSupportPhoneNumber(string $phoneNumber): static
    {
        $clone = clone $this;
        $clone->customerSupportPhoneNumber = $phoneNumber;

        return $clone;
    }

    public function brandEmail(string $email): static
    {
        $clone = clone $this;
        $clone->brandEmail = $email;

        return $clone;
    }

    public function customerSupportEmail(string $email): static
    {
        $clone = clone $this;
        $clone->customerSupportEmail = $email;

        return $clone;
    }

    public function contactNameAndTitle(string $contactNameAndTitle): static
    {
        $clone = clone $this;
        $clone->contactNameAndTitle = $contactNameAndTitle;

        return $clone;
    }

    public function companyEin(string $companyEin): static
    {
        $clone = clone $this;
        $clone->companyEin = $companyEin;

        return $clone;
    }

    public function entityType(string $entityType): static
    {
        $clone = clone $this;
        $clone->entityType = $entityType;

        return $clone;
    }

    /** @param  array{street?: string|null, city?: string|null, state?: string|null, postal_code?: string|null, country?: string|null}  $officialAddress */
    public function officialAddress(array $officialAddress): static
    {
        $clone = clone $this;
        $clone->officialAddress = $officialAddress;

        return $clone;
    }

    public function briefCompanyDescription(string $briefCompanyDescription): static
    {
        $clone = clone $this;
        $clone->briefCompanyDescription = $briefCompanyDescription;

        return $clone;
    }

    public function optInProcessDescription(string $optInProcessDescription): static
    {
        $clone = clone $this;
        $clone->optInProcessDescription = $optInProcessDescription;

        return $clone;
    }

    public function startMessage(string $startMessage): static
    {
        $clone = clone $this;
        $clone->startMessage = $startMessage;

        return $clone;
    }

    public function helpMessage(string $helpMessage): static
    {
        $clone = clone $this;
        $clone->helpMessage = $helpMessage;

        return $clone;
    }

    public function stopMessage(string $stopMessage): static
    {
        $clone = clone $this;
        $clone->stopMessage = $stopMessage;

        return $clone;
    }

    /** @param  list<string>  $sampleMessages */
    public function sampleMessages(array $sampleMessages): static
    {
        $clone = clone $this;
        $clone->sampleMessages = $sampleMessages;

        return $clone;
    }

    /** 'us' or 'eu'. Optional. */
    public function hostingRegion(string $hostingRegion): static
    {
        $clone = clone $this;
        $clone->hostingRegion = $hostingRegion;

        return $clone;
    }

    /** One of CONVERSATIONAL, SINGLE_MESSAGE, BASIC_MESSAGE. Optional. */
    public function billingCategory(string $billingCategory): static
    {
        $clone = clone $this;
        $clone->billingCategory = $billingCategory;

        return $clone;
    }

    public function optInScreenshotUrl(string $url): static
    {
        $clone = clone $this;
        $clone->optInScreenshotUrl = $url;

        return $clone;
    }

    public function save(): RcsAgentData
    {
        $data = array_filter([
            'display_name' => $this->displayName,
            'description' => $this->description,
            'agent_use_case' => $this->agentUseCase,
            'brand_name' => $this->brandName,
            'privacy_policy_url' => $this->privacyPolicyUrl,
            'terms_and_conditions_url' => $this->termsAndConditionsUrl,
            'website_url' => $this->websiteUrl,
            'brand_color' => $this->brandColor,
            'logo_url' => $this->logoUrl,
            'banner_url' => $this->bannerUrl,
            'brand_phone_number' => $this->brandPhoneNumber,
            'customer_support_phone_number' => $this->customerSupportPhoneNumber,
            'brand_email' => $this->brandEmail,
            'customer_support_email' => $this->customerSupportEmail,
            'contact_name_and_title' => $this->contactNameAndTitle,
            'company_ein' => $this->companyEin,
            'entity_type' => $this->entityType,
            'official_address' => $this->officialAddress,
            'brief_company_description' => $this->briefCompanyDescription,
            'opt_in_process_description' => $this->optInProcessDescription,
            'start_message' => $this->startMessage,
            'help_message' => $this->helpMessage,
            'stop_message' => $this->stopMessage,
            'sample_messages' => $this->sampleMessages !== [] ? $this->sampleMessages : null,
            'hosting_region' => $this->hostingRegion,
            'billing_category' => $this->billingCategory,
            'opt_in_screenshot_url' => $this->optInScreenshotUrl,
            'sandbox' => $this->sandbox,
        ], fn (mixed $value): bool => $value !== null);

        return $this->resource->addRcs($data, $this->idempotencyKey);
    }
}
