<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Sujip\SentDm\Responses\Cast;
use Sujip\SentDm\Responses\ChannelsStateData;
use Sujip\SentDm\Responses\RcsAgentData;
use Sujip\SentDm\Responses\SmsMarketData;
use Sujip\SentDm\Responses\WhatsappChannelData;

/**
 * `/v3/channels`. Not in any published `sentdm/sent-dm-php` version yet, so this calls the
 * SDK's own generic `Client::request()` (via `Resource::raw()`) instead of a typed
 * convenience method (see `CONTRIBUTING.md`). Field shapes come from Sent.dm's published
 * OpenAPI spec (api.sent.dm/swagger/v3/swagger.json).
 *
 * `compliance` on the SMS methods is intentionally untyped further than `array<string,
 * mixed>`: Sent.dm's own spec says its members are declared by the market itself, and
 * points at `Compliance::requirements()` as the authoritative set rather than a fixed
 * schema. Only US TEN_DLC carries `brand`/`campaign` sub-keys; every other market's
 * compliance is documented per-market, not here.
 *
 * @phpstan-type RcsAddressShape = array{
 *   street?: string|null, city?: string|null, state?: string|null,
 *   postal_code?: string|null, country?: string|null,
 * }
 * @phpstan-type AddRcsShape = array{
 *   brand_name: string,
 *   privacy_policy_url: string,
 *   terms_and_conditions_url: string,
 *   display_name?: string|null,
 *   description?: string|null,
 *   agent_use_case?: 'VERIFICATION'|'NOTIFICATIONS'|'MARKETING'|'MULTI_USE'|null,
 *   hosting_region?: 'us'|'eu'|null,
 *   billing_category?: 'CONVERSATIONAL'|'SINGLE_MESSAGE'|'BASIC_MESSAGE'|null,
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
 *   opt_in_screenshot_url?: string|null,
 *   start_message?: string|null,
 *   help_message?: string|null,
 *   stop_message?: string|null,
 *   sample_messages?: list<string>|null,
 *   sandbox?: bool|null,
 * }
 */
class Channels extends Resource
{
    /**
     * Same precedence as `Sent::send()`/`SenderProfileBuilder::sandbox()`: an explicit
     * `sandbox` key in `$data` (even `false`) always wins over the global `SENT_SANDBOX`
     * config. Only fills in the global default when the caller left the key out entirely,
     * and never introduces a `sandbox: false` the caller didn't ask for, so `updateSmsMarket`'s
     * "explicit null clears the field" semantics on every other key stay untouched.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSandboxDefault(array $data): array
    {
        $resolved = ($data['sandbox'] ?? $this->sandbox) ?: null;

        if ($resolved === null) {
            unset($data['sandbox']);
        } else {
            $data['sandbox'] = $resolved;
        }

        return $data;
    }

    public function get(): ChannelsStateData
    {
        return ChannelsStateData::fromArray($this->raw('get', 'v3/channels'));
    }

    /** @return list<SmsMarketData> */
    public function smsMarkets(): array
    {
        return array_map(SmsMarketData::fromArray(...), Cast::listOfArrays($this->raw('get', 'v3/channels/sms')));
    }

    public function findSmsMarket(string $country, string $type): SmsMarketData
    {
        return SmsMarketData::fromArray($this->raw('get', "v3/channels/sms/{$country}/{$type}"));
    }

    /**
     * @param  array{country: string, number_type: string, sender_value?: string|null, compliance?: array<string, mixed>|null, sandbox?: bool|null}  $data
     */
    public function addSmsMarket(array $data): SmsMarketData
    {
        return SmsMarketData::fromArray($this->raw('post', 'v3/channels/sms', body: $this->withSandboxDefault($data)));
    }

    /**
     * `country`/`type` are the path key, not sent in the body. Every other field is
     * optional and left alone when omitted.
     *
     * @param  array{compliance?: array<string, mixed>|null, sandbox?: bool|null}  $data
     */
    public function updateSmsMarket(string $country, string $type, array $data): SmsMarketData
    {
        return SmsMarketData::fromArray($this->raw('patch', "v3/channels/sms/{$country}/{$type}", body: $this->withSandboxDefault($data)));
    }

    /**
     * Everything but `waba_id` is resolved from the organization: the access token, its
     * expiry, and the business portfolio it arrived through.
     *
     * @param  array{waba_id: string, phone_number_id?: string|null, sandbox?: bool|null}  $data
     */
    public function addWhatsapp(array $data): WhatsappChannelData
    {
        return WhatsappChannelData::fromArray($this->raw('post', 'v3/channels/whatsapp', body: $this->withSandboxDefault($data)));
    }

    /**
     * Only `brand_name`/`privacy_policy_url`/`terms_and_conditions_url` are required; the
     * rest of the carrier onboarding form can be filled in later.
     *
     * @param  AddRcsShape  $data
     */
    public function addRcs(array $data): RcsAgentData
    {
        return RcsAgentData::fromArray($this->raw('post', 'v3/channels/rcs', body: $this->withSandboxDefault($data)));
    }
}
