<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * Fields from the live spec's RcsAgentState schema. `officialAddress` stays
 * `array<string, mixed>`, it's a small fixed shape (street/city/state/postal_code/
 * country) but not worth its own class for four optional strings.
 */
final class RcsAgentData
{
    /**
     * @param  array<array-key, mixed>|null  $officialAddress
     * @param  list<string>  $sampleMessages
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $status = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $displayName = null,
        public readonly ?string $description = null,
        public readonly ?string $agentUseCase = null,
        public readonly ?string $brandName = null,
        public readonly ?string $privacyPolicyUrl = null,
        public readonly ?string $termsAndConditionsUrl = null,
        public readonly ?string $websiteUrl = null,
        public readonly ?string $brandColor = null,
        public readonly ?string $brandPhoneNumber = null,
        public readonly ?string $customerSupportPhoneNumber = null,
        public readonly ?string $brandEmail = null,
        public readonly ?string $customerSupportEmail = null,
        public readonly ?string $contactNameAndTitle = null,
        public readonly ?string $companyEin = null,
        public readonly ?string $entityType = null,
        public readonly ?array $officialAddress = null,
        public readonly ?string $briefCompanyDescription = null,
        public readonly ?string $optInProcessDescription = null,
        public readonly ?string $startMessage = null,
        public readonly ?string $helpMessage = null,
        public readonly ?string $stopMessage = null,
        public readonly array $sampleMessages = [],
        public readonly ?string $createdAt = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Cast::string($data['id'] ?? null),
            status: Cast::string($data['status'] ?? null),
            phoneNumber: Cast::string($data['phone_number'] ?? null),
            displayName: Cast::string($data['display_name'] ?? null),
            description: Cast::string($data['description'] ?? null),
            agentUseCase: Cast::string($data['agent_use_case'] ?? null),
            brandName: Cast::string($data['brand_name'] ?? null),
            privacyPolicyUrl: Cast::string($data['privacy_policy_url'] ?? null),
            termsAndConditionsUrl: Cast::string($data['terms_and_conditions_url'] ?? null),
            websiteUrl: Cast::string($data['website_url'] ?? null),
            brandColor: Cast::string($data['brand_color'] ?? null),
            brandPhoneNumber: Cast::string($data['brand_phone_number'] ?? null),
            customerSupportPhoneNumber: Cast::string($data['customer_support_phone_number'] ?? null),
            brandEmail: Cast::string($data['brand_email'] ?? null),
            customerSupportEmail: Cast::string($data['customer_support_email'] ?? null),
            contactNameAndTitle: Cast::string($data['contact_name_and_title'] ?? null),
            companyEin: Cast::string($data['company_ein'] ?? null),
            entityType: Cast::string($data['entity_type'] ?? null),
            officialAddress: Cast::arr($data['official_address'] ?? null),
            briefCompanyDescription: Cast::string($data['brief_company_description'] ?? null),
            optInProcessDescription: Cast::string($data['opt_in_process_description'] ?? null),
            startMessage: Cast::string($data['start_message'] ?? null),
            helpMessage: Cast::string($data['help_message'] ?? null),
            stopMessage: Cast::string($data['stop_message'] ?? null),
            sampleMessages: Cast::listOfStrings($data['sample_messages'] ?? null),
            createdAt: Cast::string($data['created_at'] ?? null),
        );
    }
}
