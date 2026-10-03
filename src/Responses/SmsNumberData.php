<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

final class SmsNumberData
{
    public function __construct(
        public readonly ?string $senderValue = null,
        public readonly ?string $areaCode = null,
        public readonly ?string $status = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            senderValue: Cast::string($data['sender_value'] ?? null),
            areaCode: Cast::string($data['area_code'] ?? null),
            status: Cast::string($data['status'] ?? null),
        );
    }
}
