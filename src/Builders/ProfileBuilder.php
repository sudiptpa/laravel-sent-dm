<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Closure;
use SentDm\Client;
use SentDm\Profiles\ProfileCreateParams\BillingContact as BillingContactCreate;
use SentDm\Profiles\ProfileCreateParams\Brand\Compliance as ComplianceCreate;
use SentDm\Profiles\ProfileCreateParams\Brand\Contact as ContactCreate;
use SentDm\Profiles\ProfileCreateParams\PaymentDetails as PaymentDetailsCreate;
use SentDm\Profiles\ProfileCreateParams\WhatsappBusinessAccount;
use SentDm\Profiles\ProfileNewResponse;
use SentDm\Profiles\ProfileUpdateResponse;

/**
 * @deprecated Sent.dm deprecated the entire `profiles` service in its August 2026
 * platform changelog, in favor of the new `sender-profiles` resource. Still fully
 * functional; no replacement exists in the SDK yet. See `Sent::profiles()`'s
 * deprecation note.
 *
 * The SDK stopped exposing unified `BillingContactInfo`/`BrandsBrandData`/`PaymentDetails`
 * types in v0.29.0. `create()` and `update()` each get their own nested params class now
 * (`ProfileCreateParams\Brand` vs `ProfileUpdateParams\Brand`, etc.). Most of that shape is
 * identical either way (shared `TcrBrandRelationship`/`TcrVertical` enums), so importing the
 * create variant's `Compliance`/`Contact` shapes covers both, but `Brand\Business\EntityType`
 * is namespaced separately per variant, so `BusinessShape` below is written out locally with
 * `entityType` as a plain string union instead of importing either variant's enum class. This
 * builder doesn't know at billingContact()/brand()/paymentDetails() call time whether save()
 * will hit create() or update() anyway, so nothing here can commit to one variant's objects.
 *
 * @phpstan-import-type BillingContactShape from BillingContactCreate
 * @phpstan-import-type ComplianceShape from ComplianceCreate
 * @phpstan-import-type ContactShape from ContactCreate
 * @phpstan-import-type PaymentDetailsShape from PaymentDetailsCreate
 * @phpstan-import-type WhatsappBusinessAccountShape from WhatsappBusinessAccount
 *
 * @phpstan-type BusinessShape = array{
 *   city?: string|null,
 *   country?: string|null,
 *   countryOfRegistration?: string|null,
 *   entityType?: 'GOVERNMENT'|'NON_PROFIT'|'PRIVATE_PROFIT'|'PUBLIC_PROFIT'|'SOLE_PROPRIETOR'|null,
 *   legalName?: string|null,
 *   postalCode?: string|null,
 *   state?: string|null,
 *   street?: string|null,
 *   taxID?: string|null,
 *   taxIDType?: string|null,
 *   url?: string|null,
 * }
 * @phpstan-type BrandShape = array{
 *   compliance: ComplianceShape,
 *   contact: ContactShape,
 *   business?: BusinessShape|null,
 * }
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

    /** @var BillingContactShape|null */
    private ?array $billingContact = null;

    /** @var BrandShape|null */
    private ?array $brand = null;

    /** @var PaymentDetailsShape|null */
    private ?array $paymentDetails = null;

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
        private readonly ?string $profileId = null,
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

    /** @param  BillingContactShape  $billingContact */
    public function billingContact(array $billingContact): static
    {
        $clone = clone $this;
        $clone->billingContact = $billingContact;

        return $clone;
    }

    /** @param  BrandShape  $brand */
    public function brand(array $brand): static
    {
        $clone = clone $this;
        $clone->brand = $brand;

        return $clone;
    }

    /** @param  PaymentDetailsShape  $paymentDetails */
    public function paymentDetails(array $paymentDetails): static
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

    public function save(): ProfileNewResponse|ProfileUpdateResponse
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
                xProfileID: $this->profileId,
            );

            if ($this->onSaved !== null) {
                ($this->onSaved)();
            }

            return $result;
        }

        $result = $this->client->profiles->create(
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
            xProfileID: $this->profileId,
        );

        if ($this->onSaved !== null) {
            ($this->onSaved)();
        }

        return $result;
    }
}
