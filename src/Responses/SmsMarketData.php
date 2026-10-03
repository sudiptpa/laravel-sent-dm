<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * Fields from the live spec's SmsMarketState schema. `compliance` stays
 * `array<string, mixed>`: shape varies per market, only US TEN_DLC carries
 * brand/campaign sub-keys (see Compliance::requirements()).
 */
final class SmsMarketData
{
    /**
     * @param  list<SmsNumberData>  $numbers
     * @param  array<array-key, mixed>|null  $compliance
     */
    public function __construct(
        public readonly ?string $country = null,
        public readonly ?string $numberType = null,
        public readonly ?string $senderValue = null,
        public readonly array $numbers = [],
        public readonly ?string $status = null,
        public readonly ?string $reasonCode = null,
        public readonly ?string $reason = null,
        public readonly ?string $note = null,
        public readonly ?array $compliance = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            country: Cast::string($data['country'] ?? null),
            numberType: Cast::string($data['number_type'] ?? null),
            senderValue: Cast::string($data['sender_value'] ?? null),
            numbers: array_map(SmsNumberData::fromArray(...), Cast::listOfArrays($data['numbers'] ?? null)),
            status: Cast::string($data['status'] ?? null),
            reasonCode: Cast::string($data['reason_code'] ?? null),
            reason: Cast::string($data['reason'] ?? null),
            note: Cast::string($data['note'] ?? null),
            compliance: Cast::arr($data['compliance'] ?? null),
        );
    }
}
