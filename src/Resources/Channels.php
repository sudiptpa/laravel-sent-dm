<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use InvalidArgumentException;
use SentDm\Channels\APIResponseOfChannelsState;
use SentDm\Channels\Rcs\APIResponseOfRcsAgent;
use SentDm\Channels\Rcs\RcCreateParams\AgentUseCase;
use SentDm\Channels\SMS\APIResponseOfListOfSMSMarket;
use SentDm\Channels\SMS\APIResponseOfSMSMarket;
use SentDm\Channels\SMS\MarketCompliance;
use SentDm\Channels\SMS\SMSCreateParams\NumberType;
use SentDm\Channels\SMS\SMSNumberRequest;
use SentDm\Channels\SMS\SMSUpdateParams;
use SentDm\Channels\Whatsapp\APIResponseOfWhatsappChannel;
use SentDm\Core\Conversion;
use SentDm\Core\FileParam;
use Sujip\SentDm\Builders\RcsAgentBuilder;
use Sujip\SentDm\Builders\SmsMarketBuilder;
use Sujip\SentDm\Builders\WhatsappChannelBuilder;
use Sujip\SentDm\Support\Sandbox;

/**
 * `/v3/channels`. A document upload and an update that clears a value with null use `rawModel()`.
 *
 * @phpstan-import-type MarketComplianceShape from MarketCompliance
 * @phpstan-import-type SMSNumberRequestShape from SMSNumberRequest
 *
 * @phpstan-type RcsAddressShape = array{
 *   street?: string|null, city?: string|null, state?: string|null,
 *   postal_code?: string|null, postalCode?: string|null, country?: string|null,
 * }
 * @phpstan-type AddRcsShape = array{
 *   display_name?: string|null,
 *   description?: string|null,
 *   agent_use_case?: string|AgentUseCase|null,
 *   brand_name?: string|null,
 *   privacy_policy_url?: string|null,
 *   terms_and_conditions_url?: string|null,
 *   website_url?: string|null,
 *   brand_color?: string|null,
 *   logo_url?: string|null,
 *   banner_url?: string|null,
 *   brand_phone_number?: string|null,
 *   customer_support_phone_number?: string|null,
 *   brand_email?: string|null,
 *   customer_support_email?: string|null,
 *   contact_name_and_title?: string|null,
 *   company_ein?: string|null,
 *   entity_type?: string|null,
 *   official_address?: RcsAddressShape|null,
 *   brief_company_description?: string|null,
 *   opt_in_process_description?: string|null,
 *   start_message?: string|null,
 *   help_message?: string|null,
 *   stop_message?: string|null,
 *   sample_messages?: list<string>|null,
 *   opt_in_screenshot_url?: string|null,
 *   sandbox?: bool|null,
 * }
 * @phpstan-type AddSmsMarketShape = array{
 *   country?: string|null,
 *   number_type?: string|NumberType|null,
 *   sender_value?: string|null,
 *   area_codes?: list<string>|null,
 *   numbers?: list<SMSNumberRequestShape>|null,
 *   compliance?: MarketComplianceShape|null,
 *   sandbox?: bool|null,
 * }
 */
class Channels extends Resource
{
    /**
     * An explicit `sandbox` in `$data`, even `false`, wins over the global default.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSandboxDefault(array $data): array
    {
        $resolved = $this->resolvedSandbox($data);

        if ($resolved === null) {
            unset($data['sandbox']);
        } else {
            $data['sandbox'] = $resolved;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function resolvedSandbox(array $data): ?bool
    {
        $explicit = $data['sandbox'] ?? null;

        return Sandbox::resolve(is_bool($explicit) ? $explicit : null, $this->sandbox);
    }

    public function get(): APIResponseOfChannelsState
    {
        return $this->client->channels->list(xProfileID: $this->orgProfileId);
    }

    public function smsMarkets(): APIResponseOfListOfSMSMarket
    {
        return $this->client->channels->sms->list(xProfileID: $this->orgProfileId);
    }

    public function findSmsMarket(string $country, string $type): APIResponseOfSMSMarket
    {
        return $this->client->channels->sms->retrieve(type: $type, country: $country, xProfileID: $this->orgProfileId);
    }

    /**
     * A `FileParam` in `$data` sends a multipart request. Keep `number_type` and `sender_value`
     * as usual, they are renamed for the form. No `compliance`, `area_codes` or `numbers` alongside a file.
     *
     * @param  array<string, mixed>  $data  An `AddSmsMarketShape`, plus `FileParam` values for documents.
     */
    public function addSmsMarket(array $data, ?string $idempotencyKey = null): APIResponseOfSMSMarket
    {
        $this->rejectUnknownKeys('addSmsMarket', $data, ['country', 'number_type', 'sender_value', 'area_codes', 'numbers', 'compliance', 'sandbox'], allowFiles: true);

        $hasFile = in_array(true, array_map(fn (mixed $value): bool => $value instanceof FileParam, $data), true);

        if ($hasFile) {
            return $this->addSmsMarketWithDocuments($data, $idempotencyKey);
        }

        /** @var AddSmsMarketShape $data */
        return $this->client->channels->sms->create(
            numberType: $this->smsNumberType($data),
            areaCodes: $data['area_codes'] ?? null,
            compliance: $data['compliance'] ?? null,
            country: $data['country'] ?? null,
            numbers: $data['numbers'] ?? null,
            sandbox: $this->resolvedSandbox($data),
            senderValue: $data['sender_value'] ?? null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * A key the typed call does not read would be dropped without a word, so refuse it.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $allowed
     */
    private function rejectUnknownKeys(string $method, array $data, array $allowed, bool $allowFiles = false): void
    {
        $unknown = array_values(array_filter(
            array_keys($data),
            fn (int|string $key): bool => ! in_array($key, $allowed, true)
                && ! ($allowFiles && $data[$key] instanceof FileParam),
        ));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                "Unknown key(s) for {$method}(): ".implode(', ', $unknown).'. Allowed: '.implode(', ', $allowed).'.'
            );
        }
    }

    /** @param  array<string, mixed>  $data */
    private function smsNumberType(array $data): NumberType
    {
        $given = $data['number_type'] ?? null;

        if ($given instanceof NumberType) {
            return $given;
        }

        $numberType = is_string($given) ? NumberType::tryFrom(strtoupper($given)) : null;

        if ($numberType === null) {
            throw new InvalidArgumentException('A valid number_type is required for addSmsMarket(). One of: '.implode(', ', array_column(NumberType::cases(), 'value')).'.');
        }

        return $numberType;
    }

    /** @param  array<string, mixed>  $data */
    private function addSmsMarketWithDocuments(array $data, ?string $idempotencyKey): APIResponseOfSMSMarket
    {
        if (($data['compliance'] ?? null) !== null) {
            throw new InvalidArgumentException('compliance is not supported together with a document upload on addSmsMarket().');
        }

        foreach (['area_codes', 'numbers'] as $key) {
            if (($data[$key] ?? null) !== null) {
                throw new InvalidArgumentException("{$key} is not supported together with a document upload on addSmsMarket().");
            }
        }

        $body = $this->withSandboxDefault($data);
        $body['number_type'] = $this->smsNumberType($data)->value;
        unset($body['compliance']);

        $renamed = [];
        foreach ($body as $key => $value) {
            $renamed[match ($key) {
                'number_type' => 'numberType',
                'sender_value' => 'senderValue',
                default => $key,
            }] = $value;
        }

        $headers = ['Content-Type' => 'multipart/form-data'] + $this->idempotencyHeader($idempotencyKey);

        return $this->rawModel(APIResponseOfSMSMarket::class, 'post', 'v3/channels/sms', body: $renamed, headers: $headers);
    }

    /**
     * A key left out is left alone, and an explicit `null` clears it, nested keys included
     * (`['compliance' => ['brand' => null]]`).
     *
     * @param  array{compliance?: MarketComplianceShape|null, sandbox?: bool|null}  $data
     */
    public function updateSmsMarket(string $country, string $type, array $data, ?string $idempotencyKey = null): APIResponseOfSMSMarket
    {
        $this->rejectUnknownKeys('updateSmsMarket', $data, ['compliance', 'sandbox']);

        if (array_key_exists('compliance', $data) && $this->containsNull($data['compliance'])) {
            return $this->updateSmsMarketKeepingNulls($country, $type, $data, $idempotencyKey);
        }

        return $this->client->channels->sms->update(
            type: $type,
            country: $country,
            compliance: $data['compliance'] ?? null,
            sandbox: $this->resolvedSandbox($data),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * The typed call drops every null, so a clearing update goes out directly.
     *
     * @param  array{compliance?: MarketComplianceShape|null, sandbox?: bool|null}  $data
     */
    private function updateSmsMarketKeepingNulls(string $country, string $type, array $data, ?string $idempotencyKey): APIResponseOfSMSMarket
    {
        $params = new SMSUpdateParams;
        $params['compliance'] = $data['compliance'] ?? null;

        $sandbox = $this->resolvedSandbox($data);

        if ($sandbox !== null) {
            $params['sandbox'] = $sandbox;
        }

        /** @var array<string, mixed> $body */
        $body = Conversion::dump(SMSUpdateParams::class, $params->toProperties());

        return $this->rawModel(
            APIResponseOfSMSMarket::class,
            'patch',
            'v3/channels/sms/'.rawurlencode($country).'/'.rawurlencode($type),
            body: $body,
            headers: $this->idempotencyHeader($idempotencyKey),
        );
    }

    private function containsNull(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsNull($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Connects one of your organization's shared WhatsApp Business Accounts (`waba_id`) to a
     * sender profile, so scope the call with `profile()`. Without `phone_number_id` the channel
     * reads `ACTION_NEEDED` until a number is named. The access token, its expiry and the
     * business portfolio are resolved from the organization.
     *
     * @param  array{waba_id?: string|null, phone_number_id?: string|null, sandbox?: bool|null}  $data
     */
    public function addWhatsapp(array $data, ?string $idempotencyKey = null): APIResponseOfWhatsappChannel
    {
        $this->rejectUnknownKeys('addWhatsapp', $data, ['waba_id', 'phone_number_id', 'sandbox']);

        if (! isset($data['waba_id']) || $data['waba_id'] === '') {
            throw new InvalidArgumentException('Missing required field for addWhatsapp(): waba_id.');
        }

        return $this->client->channels->whatsapp->create(
            phoneNumberID: $data['phone_number_id'] ?? null,
            sandbox: $this->resolvedSandbox($data),
            wabaID: $data['waba_id'],
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * The full RCS brand-registration form. A request missing any of these 400s,
     * naming every field it's missing. `opt_in_screenshot_url` is optional.
     *
     * @var list<string>
     */
    private const REQUIRED_RCS_FIELDS = [
        'display_name', 'description', 'agent_use_case', 'brand_name', 'privacy_policy_url',
        'terms_and_conditions_url', 'website_url', 'brand_color', 'logo_url', 'banner_url',
        'brand_phone_number', 'customer_support_phone_number', 'brand_email',
        'customer_support_email', 'contact_name_and_title', 'company_ein', 'entity_type',
        'official_address', 'brief_company_description', 'opt_in_process_description',
        'start_message', 'help_message', 'stop_message', 'sample_messages',
    ];

    /**
     * Every key is optional in the type, so the required-field check below stays real.
     *
     * @param  AddRcsShape  $data
     */
    public function addRcs(array $data, ?string $idempotencyKey = null): APIResponseOfRcsAgent
    {
        $this->rejectUnknownKeys('addRcs', $data, [...self::REQUIRED_RCS_FIELDS, 'opt_in_screenshot_url', 'sandbox']);

        $missing = array_values(array_filter(
            self::REQUIRED_RCS_FIELDS,
            fn (string $field): bool => ($data[$field] ?? null) === null,
        ));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Missing required field(s) for addRcs(): '.implode(', ', $missing).'.'
            );
        }

        $address = $data['official_address'] ?? null;
        $given = $data['agent_use_case'] ?? null;
        $useCase = $given instanceof AgentUseCase ? $given : AgentUseCase::tryFrom(strtoupper((string) $given));

        if ($useCase === null) {
            throw new InvalidArgumentException('agent_use_case must be one of: '.implode(', ', array_column(AgentUseCase::cases(), 'value')).'.');
        }

        return $this->client->channels->rcs->create(
            agentUseCase: $useCase,
            bannerURL: $data['banner_url'] ?? null,
            brandColor: $data['brand_color'] ?? null,
            brandEmail: $data['brand_email'] ?? null,
            brandName: $data['brand_name'] ?? null,
            brandPhoneNumber: $data['brand_phone_number'] ?? null,
            briefCompanyDescription: $data['brief_company_description'] ?? null,
            companyEin: $data['company_ein'] ?? null,
            contactNameAndTitle: $data['contact_name_and_title'] ?? null,
            customerSupportEmail: $data['customer_support_email'] ?? null,
            customerSupportPhoneNumber: $data['customer_support_phone_number'] ?? null,
            description: $data['description'] ?? null,
            displayName: $data['display_name'] ?? null,
            entityType: $data['entity_type'] ?? null,
            helpMessage: $data['help_message'] ?? null,
            logoURL: $data['logo_url'] ?? null,
            officialAddress: $address === null ? null : array_filter([
                'city' => $address['city'] ?? null,
                'country' => $address['country'] ?? null,
                'postalCode' => $address['postal_code'] ?? $address['postalCode'] ?? null,
                'state' => $address['state'] ?? null,
                'street' => $address['street'] ?? null,
            ], fn (?string $value): bool => $value !== null),
            optInProcessDescription: $data['opt_in_process_description'] ?? null,
            optInScreenshotURL: $data['opt_in_screenshot_url'] ?? null,
            privacyPolicyURL: $data['privacy_policy_url'] ?? null,
            sampleMessages: $data['sample_messages'] ?? null,
            sandbox: $this->resolvedSandbox($data),
            startMessage: $data['start_message'] ?? null,
            stopMessage: $data['stop_message'] ?? null,
            termsAndConditionsURL: $data['terms_and_conditions_url'] ?? null,
            websiteURL: $data['website_url'] ?? null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /** Fluent alternative to addWhatsapp(). */
    public function whatsapp(): WhatsappChannelBuilder
    {
        return new WhatsappChannelBuilder($this);
    }

    /** Fluent alternative to addRcs(). */
    public function rcs(): RcsAgentBuilder
    {
        return new RcsAgentBuilder($this);
    }

    public function voice(): Voice
    {
        $voice = new Voice($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);

        return $this->orgProfileId !== null ? $voice->profile($this->orgProfileId) : $voice;
    }

    /** Fluent alternative to addSmsMarket(). */
    public function smsMarket(): SmsMarketBuilder
    {
        return new SmsMarketBuilder($this, mode: 'create');
    }

    /** Fluent alternative to updateSmsMarket(). */
    public function updateSmsMarketBuilder(string $country, string $type): SmsMarketBuilder
    {
        return new SmsMarketBuilder($this, mode: 'update', updateCountry: $country, updateNumberType: $type);
    }
}
