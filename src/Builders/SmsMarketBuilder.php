<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use InvalidArgumentException;
use SentDm\Core\FileParam;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Responses\SmsMarketData;

/**
 * Fluent alternative to `Channels::addSmsMarket(array $data)` and
 * `Channels::updateSmsMarket($country, $type, array $data)`, both of which stay as-is.
 *
 * Update mode (constructed via `Channels::updateSmsMarketBuilder()`) has partial-update
 * semantics: only the fields set on this builder are sent, everything else on the
 * market is left alone. `PATCH /v3/channels/sms/{country}/{type}` only accepts
 * `compliance` and `sandbox` (confirmed live: `sender_value` 400s there with "is not
 * something this call changes"), so `country()`, `numberType()`, `senderValue()`,
 * `areaCodes()`, and `attach()` all throw in update mode instead of being silently
 * dropped or sent and rejected by the API.
 */
class SmsMarketBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $country = null;

    private ?string $numberType = null;

    private ?string $senderValue = null;

    /** @var list<string>|null */
    private ?array $areaCodes = null;

    /** @var array<string, mixed>|null */
    private ?array $compliance = null;

    /** @var array<string, FileParam> */
    private array $attachments = [];

    public function __construct(
        private readonly Channels $resource,
        private readonly string $mode,
        private readonly ?string $updateCountry = null,
        private readonly ?string $updateNumberType = null,
    ) {}

    /** Create mode only. save() throws on update, where the market is identified by the path instead. */
    public function country(string $country): static
    {
        $clone = clone $this;
        $clone->country = $country;

        return $clone;
    }

    /** Create mode only. save() throws on update, where the market is identified by the path instead. */
    public function numberType(string $numberType): static
    {
        $clone = clone $this;
        $clone->numberType = $numberType;

        return $clone;
    }

    /** Create mode only. save() throws on update, the API rejects it there. */
    public function senderValue(string $senderValue): static
    {
        $clone = clone $this;
        $clone->senderValue = $senderValue;

        return $clone;
    }

    /**
     * Create mode only. save() throws on update, the API rejects it there.
     *
     * @param  list<string>  $areaCodes
     */
    public function areaCodes(array $areaCodes): static
    {
        $clone = clone $this;
        $clone->areaCodes = $areaCodes;

        return $clone;
    }

    /**
     * Members are declared by the market itself. Call `Sent::compliance()->requirements()`
     * for the authoritative set before building this. Not supported together with
     * attach(), the API rejects that combination.
     *
     * @param  array<string, mixed>  $compliance
     */
    public function compliance(array $compliance): static
    {
        $clone = clone $this;
        $clone->compliance = $compliance;

        return $clone;
    }

    /**
     * A document this market requires, keyed by the compliance field name it satisfies.
     * Switches save() to a `multipart/form-data` request. Not supported together with
     * compliance(). Create mode only, save() throws on update.
     */
    public function attach(string $complianceKey, FileParam $file): static
    {
        $clone = clone $this;
        $clone->attachments[$complianceKey] = $file;

        return $clone;
    }

    public function save(): SmsMarketData
    {
        if ($this->mode === 'update') {
            return $this->saveUpdate();
        }

        return $this->saveCreate();
    }

    private function saveCreate(): SmsMarketData
    {
        if ($this->country === null) {
            throw new InvalidArgumentException('A country is required to add an SMS market. Call country() before save().');
        }

        if ($this->numberType === null) {
            throw new InvalidArgumentException('A number type is required to add an SMS market. Call numberType() before save().');
        }

        $data = array_filter([
            'country' => $this->country,
            'number_type' => $this->numberType,
            'sender_value' => $this->senderValue,
            'area_codes' => $this->areaCodes,
            'compliance' => $this->compliance,
            'sandbox' => $this->sandbox,
        ], fn (mixed $value): bool => $value !== null);

        foreach ($this->attachments as $key => $file) {
            $data[$key] = $file;
        }

        return $this->resource->addSmsMarket($data, $this->idempotencyKey);
    }

    private function saveUpdate(): SmsMarketData
    {
        if ($this->country !== null || $this->numberType !== null) {
            throw new InvalidArgumentException('country() and numberType() are not supported on update(). The market is identified by the country/type given to updateSmsMarketBuilder(); use addSmsMarket() to add a different one.');
        }

        if ($this->senderValue !== null || $this->areaCodes !== null) {
            throw new InvalidArgumentException('senderValue() and areaCodes() are not supported on update(). PATCH /v3/channels/sms/{country}/{type} only accepts compliance and sandbox, confirmed live: the API rejects a sender value there with "is not something this call changes".');
        }

        if ($this->attachments !== []) {
            throw new InvalidArgumentException('attach() is not supported on update(). PATCH /v3/channels/sms/{country}/{type} only accepts application/json, not a file upload.');
        }

        /** @var string $country */
        $country = $this->updateCountry;
        /** @var string $numberType */
        $numberType = $this->updateNumberType;

        $data = array_filter([
            'compliance' => $this->compliance,
            'sandbox' => $this->sandbox,
        ], fn (mixed $value): bool => $value !== null);

        return $this->resource->updateSmsMarket($country, $numberType, $data, $this->idempotencyKey);
    }
}
