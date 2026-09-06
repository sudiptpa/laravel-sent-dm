<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

final class WhatsappChannelData
{
    public function __construct(
        public readonly ?string $wabaId = null,
        public readonly ?string $phoneNumberId = null,
        public readonly ?string $solutionId = null,
        public readonly ?string $ownerBusinessId = null,
        public readonly ?string $status = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            wabaId: Cast::string($data['waba_id'] ?? null),
            phoneNumberId: Cast::string($data['phone_number_id'] ?? null),
            solutionId: Cast::string($data['solution_id'] ?? null),
            ownerBusinessId: Cast::string($data['owner_business_id'] ?? null),
            status: Cast::string($data['status'] ?? null),
        );
    }
}
