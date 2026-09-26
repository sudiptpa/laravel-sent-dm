<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

final class MmsMarketData
{
    public function __construct(
        public readonly ?string $country = null,
        public readonly ?string $numberType = null,
        public readonly ?string $senderValue = null,
        public readonly ?string $status = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            country: Cast::string($data['country'] ?? null),
            numberType: Cast::string($data['number_type'] ?? null),
            senderValue: Cast::string($data['sender_value'] ?? null),
            status: Cast::string($data['status'] ?? null),
        );
    }
}
