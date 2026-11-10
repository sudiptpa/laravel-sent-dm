<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use InvalidArgumentException;
use SentDm\Channels\APIResponseOfChannelsState;
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
use Sujip\SentDm\Responses\RcsAgentData;
use Sujip\SentDm\Support\Sandbox;

/**
 * `/v3/channels`. Reads, SMS, and WhatsApp use the typed SDK calls. A document upload and an
 * update that clears a value with null are sent directly. RCS still goes through
 * `Resource::raw()`.
 *
 * `compliance` members are declared by the market itself. `Compliance::requirements()` is
 * the authoritative set, not a fixed shape here.
 *
 * @phpstan-import-type MarketComplianceShape from MarketCompliance
 * @phpstan-import-type SMSNumberRequestShape from SMSNumberRequest
 *
 * @phpstan-type RcsAddressShape = array{
 *   street?: string|null, city?: string|null, state?: string|null,
 *   postal_code?: string|null, country?: string|null,
 * }
 * @phpstan-type AddSmsMarketShape = array{
 *   country?: string|null,
 *   number_type?: string|null,
 *   sender_value?: string|null,
 *   area_codes?: list<string>|null,
 *   numbers?: list<SMSNumberRequestShape>|null,
 *   compliance?: MarketComplianceShape|null,
 *   sandbox?: bool|null,
 * }
 * @phpstan-type AddRcsShape = array{
 *   display_name: string,
 *   description: string,
 *   agent_use_case: 'VERIFICATION'|'NOTIFICATIONS'|'MARKETING'|'MULTI_USE',
 *   brand_name: string,
 *   privacy_policy_url: string,
 *   terms_and_conditions_url: string,
 *   website_url: string,
 *   brand_color: string,
 *   logo_url: string,
 *   banner_url: string,
 *   brand_phone_number: string,
 *   customer_support_phone_number: string,
 *   brand_email: string,
 *   customer_support_email: string,
 *   contact_name_and_title: string,
 *   company_ein: string,
 *   entity_type: string,
 *   official_address: RcsAddressShape,
 *   brief_company_description: string,
 *   opt_in_process_description: string,
 *   start_message: string,
 *   help_message: string,
 *   stop_message: string,
 *   sample_messages: list<string>,
 *   opt_in_screenshot_url?: string|null,
 *   sandbox?: bool|null,
 * }
 */
class Channels extends Resource
{
    /**
     * Fills in the global sandbox default only when the caller left the key out, and never
     * introduces a `sandbox: false` the caller did not ask for. An explicit `sandbox` in
     * `$data`, even `false`, always wins over the global `SENT_SANDBOX` config.
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
     * A `FileParam` in `$data`, keyed by its compliance field name, sends a
     * `multipart/form-data` request instead. The typed SDK call has no file support, so
     * that path goes through `raw()`. This form reads `numberType`/`senderValue`, not
     * `number_type`/`sender_value`. No `compliance` alongside a file.
     *
     * @param  array<string, mixed>  $data  An `AddSmsMarketShape`, plus `FileParam` values for documents.
     */
    public function addSmsMarket(array $data, ?string $idempotencyKey = null): APIResponseOfSMSMarket
    {
        $hasFile = in_array(true, array_map(fn (mixed $value): bool => $value instanceof FileParam, $data), true);

        if ($hasFile) {
            return $this->addSmsMarketWithDocuments($data, $idempotencyKey);
        }

        /** @var AddSmsMarketShape $data */
        $numberType = NumberType::tryFrom(strtoupper((string) ($data['number_type'] ?? '')));

        if ($numberType === null) {
            throw new InvalidArgumentException('A valid number_type is required for addSmsMarket(). One of: '.implode(', ', array_column(NumberType::cases(), 'value')).'.');
        }

        return $this->client->channels->sms->create(
            numberType: $numberType,
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

    /** @param  array<string, mixed>  $data */
    private function addSmsMarketWithDocuments(array $data, ?string $idempotencyKey): APIResponseOfSMSMarket
    {
        if (($data['compliance'] ?? null) !== null) {
            throw new InvalidArgumentException('compliance is not supported together with a document upload on addSmsMarket().');
        }

        $body = $this->withSandboxDefault($data);
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

        /** @var APIResponseOfSMSMarket */
        return Conversion::coerce(APIResponseOfSMSMarket::class, [
            'success' => true,
            'data' => $this->raw('post', 'v3/channels/sms', body: $renamed, headers: $headers),
        ]);
    }

    /**
     * `country`/`type` are the path key, not sent in the body. A key left out is left
     * alone, and an explicit `null` clears it, nested keys included
     * (`['compliance' => ['brand' => null]]`).
     *
     * @param  array{compliance?: MarketComplianceShape|null, sandbox?: bool|null}  $data
     */
    public function updateSmsMarket(string $country, string $type, array $data, ?string $idempotencyKey = null): APIResponseOfSMSMarket
    {
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
     * The typed `update()` drops every null before sending, and the API reads a missing key
     * as "leave it alone" and an explicit null as "clear it". A request that clears
     * something is therefore sent directly, with the body built by the SDK's own
     * conversion so both key spellings reach the wire the same way.
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

        /** @var APIResponseOfSMSMarket */
        return Conversion::coerce(APIResponseOfSMSMarket::class, [
            'success' => true,
            'data' => $this->raw(
                'patch',
                'v3/channels/sms/'.rawurlencode($country).'/'.rawurlencode($type),
                body: $body,
                headers: $this->idempotencyHeader($idempotencyKey),
            ),
        ]);
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
     * Everything but `waba_id` is resolved from the organization: the access token, its
     * expiry, and the business portfolio it arrived through.
     *
     * @param  array{waba_id?: string|null, phone_number_id?: string|null, sandbox?: bool|null}  $data
     */
    public function addWhatsapp(array $data, ?string $idempotencyKey = null): APIResponseOfWhatsappChannel
    {
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
     * The intended shape is `AddRcsShape`, documented above. Typed as a plain array
     * here, not that shape, so the required-field check below actually runs: PHPStan
     * would otherwise treat the shape's own required keys as guaranteed present and
     * mark the check dead code, defeating the point of checking at runtime.
     *
     * @param  array<string, mixed>  $data
     */
    public function addRcs(array $data, ?string $idempotencyKey = null): RcsAgentData
    {
        $missing = array_values(array_filter(
            self::REQUIRED_RCS_FIELDS,
            fn (string $field): bool => ($data[$field] ?? null) === null,
        ));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Missing required field(s) for addRcs(): '.implode(', ', $missing).'.'
            );
        }

        return RcsAgentData::fromArray($this->raw('post', 'v3/channels/rcs', body: $this->withSandboxDefault($data), headers: $this->idempotencyHeader($idempotencyKey)));
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
