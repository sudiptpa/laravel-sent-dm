<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Closure;
use SentDm\Client;
use SentDm\Profiles\APIResponseOfProfileDetail;
use SentDm\Profiles\BillingContactInfo;
use SentDm\Profiles\BrandsBrandData;
use SentDm\Profiles\PaymentDetails;
use SentDm\Profiles\ProfileCreateParams\WhatsappBusinessAccount;

/**
 * @phpstan-import-type BillingContactInfoShape from BillingContactInfo
 * @phpstan-import-type BrandsBrandDataShape from BrandsBrandData
 * @phpstan-import-type PaymentDetailsShape from PaymentDetails
 * @phpstan-import-type WhatsappBusinessAccountShape from WhatsappBusinessAccount
 */
class ProfileBuilder
{
    private ?string $name = null;

    private ?string $description = null;

    private ?string $shortName = null;

    private ?string $icon = null;

    private ?string $billingModel = null;

    private ?bool $inheritContacts = null;

    private ?bool $inheritTemplates = null;

    private ?bool $inheritTcrBrand = null;

    private ?bool $inheritTcrCampaign = null;

    private ?bool $allowContactSharing = null;

    private ?bool $allowTemplateSharing = null;

    /** @var BillingContactInfo|BillingContactInfoShape|null */
    private BillingContactInfo|array|null $billingContact = null;

    /** @var BrandsBrandData|BrandsBrandDataShape|null */
    private BrandsBrandData|array|null $brand = null;

    /** @var PaymentDetails|PaymentDetailsShape|null */
    private PaymentDetails|array|null $paymentDetails = null;

    /** @var WhatsappBusinessAccount|WhatsappBusinessAccountShape|null */
    private WhatsappBusinessAccount|array|null $whatsappBusinessAccount = null;

    // Update-only fields
    private ?bool $allowNumberChangeDuringOnboarding = null;

    private ?string $sendingPhoneNumber = null;

    private ?string $sendingPhoneNumberProfileId = null;

    private ?string $sendingWhatsappNumberProfileId = null;

    private ?string $whatsappPhoneNumber = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
        private readonly ?Closure $onSaved = null,
    ) {}

    public function name(string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function description(string $description): static
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function shortName(string $shortName): static
    {
        $clone = clone $this;
        $clone->shortName = $shortName;

        return $clone;
    }

    public function icon(string $icon): static
    {
        $clone = clone $this;
        $clone->icon = $icon;

        return $clone;
    }

    public function billingModel(string $billingModel): static
    {
        $clone = clone $this;
        $clone->billingModel = $billingModel;

        return $clone;
    }

    public function inheritContacts(bool $value = true): static
    {
        $clone = clone $this;
        $clone->inheritContacts = $value;

        return $clone;
    }

    public function inheritTemplates(bool $value = true): static
    {
        $clone = clone $this;
        $clone->inheritTemplates = $value;

        return $clone;
    }

    public function inheritTcrBrand(bool $value = true): static
    {
        $clone = clone $this;
        $clone->inheritTcrBrand = $value;

        return $clone;
    }

    public function inheritTcrCampaign(bool $value = true): static
    {
        $clone = clone $this;
        $clone->inheritTcrCampaign = $value;

        return $clone;
    }

    public function allowContactSharing(bool $value = true): static
    {
        $clone = clone $this;
        $clone->allowContactSharing = $value;

        return $clone;
    }

    public function allowTemplateSharing(bool $value = true): static
    {
        $clone = clone $this;
        $clone->allowTemplateSharing = $value;

        return $clone;
    }

    /** @param BillingContactInfo|BillingContactInfoShape $billingContact */
    public function billingContact(BillingContactInfo|array $billingContact): static
    {
        $clone = clone $this;
        $clone->billingContact = $billingContact;

        return $clone;
    }

    /** @param BrandsBrandData|BrandsBrandDataShape $brand */
    public function brand(BrandsBrandData|array $brand): static
    {
        $clone = clone $this;
        $clone->brand = $brand;

        return $clone;
    }

    /** @param PaymentDetails|PaymentDetailsShape $paymentDetails */
    public function paymentDetails(PaymentDetails|array $paymentDetails): static
    {
        $clone = clone $this;
        $clone->paymentDetails = $paymentDetails;

        return $clone;
    }

    /** @param WhatsappBusinessAccount|WhatsappBusinessAccountShape $whatsappBusinessAccount */
    public function whatsappBusinessAccount(WhatsappBusinessAccount|array $whatsappBusinessAccount): static
    {
        $clone = clone $this;
        $clone->whatsappBusinessAccount = $whatsappBusinessAccount;

        return $clone;
    }

    public function allowNumberChangeDuringOnboarding(bool $value = true): static
    {
        $clone = clone $this;
        $clone->allowNumberChangeDuringOnboarding = $value;

        return $clone;
    }

    public function sendingPhoneNumber(string $phoneNumber): static
    {
        $clone = clone $this;
        $clone->sendingPhoneNumber = $phoneNumber;

        return $clone;
    }

    public function sendingPhoneNumberProfileId(string $profileId): static
    {
        $clone = clone $this;
        $clone->sendingPhoneNumberProfileId = $profileId;

        return $clone;
    }

    public function sendingWhatsappNumberProfileId(string $profileId): static
    {
        $clone = clone $this;
        $clone->sendingWhatsappNumberProfileId = $profileId;

        return $clone;
    }

    public function whatsappPhoneNumber(string $phoneNumber): static
    {
        $clone = clone $this;
        $clone->whatsappPhoneNumber = $phoneNumber;

        return $clone;
    }

    public function save(): APIResponseOfProfileDetail
    {
        if ($this->id !== null) {
            $result = $this->client->profiles->update(
                profileID: $this->id,
                allowContactSharing: $this->allowContactSharing,
                allowNumberChangeDuringOnboarding: $this->allowNumberChangeDuringOnboarding,
                allowTemplateSharing: $this->allowTemplateSharing,
                billingContact: $this->billingContact,
                billingModel: $this->billingModel,
                brand: $this->brand,
                description: $this->description,
                icon: $this->icon,
                inheritContacts: $this->inheritContacts,
                inheritTcrBrand: $this->inheritTcrBrand,
                inheritTcrCampaign: $this->inheritTcrCampaign,
                inheritTemplates: $this->inheritTemplates,
                name: $this->name,
                paymentDetails: $this->paymentDetails,
                sendingPhoneNumber: $this->sendingPhoneNumber,
                sendingPhoneNumberProfileID: $this->sendingPhoneNumberProfileId,
                sendingWhatsappNumberProfileID: $this->sendingWhatsappNumberProfileId,
                shortName: $this->shortName,
                whatsappPhoneNumber: $this->whatsappPhoneNumber,
            );

            if ($this->onSaved !== null) {
                ($this->onSaved)();
            }

            return $result;
        }

        return $this->client->profiles->create(
            allowContactSharing: $this->allowContactSharing,
            allowTemplateSharing: $this->allowTemplateSharing,
            billingContact: $this->billingContact,
            billingModel: $this->billingModel,
            brand: $this->brand,
            description: $this->description,
            icon: $this->icon,
            inheritContacts: $this->inheritContacts,
            inheritTcrBrand: $this->inheritTcrBrand,
            inheritTcrCampaign: $this->inheritTcrCampaign,
            inheritTemplates: $this->inheritTemplates,
            name: $this->name,
            paymentDetails: $this->paymentDetails,
            shortName: $this->shortName,
            whatsappBusinessAccount: $this->whatsappBusinessAccount,
        );
    }
}
