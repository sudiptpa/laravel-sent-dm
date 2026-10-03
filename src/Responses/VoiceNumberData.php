<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

final class VoiceNumberData
{
    public function __construct(
        public readonly ?string $number = null,
        public readonly ?string $status = null,
        public readonly ?bool $defaultForAppCalls = null,
        public readonly ?string $callbackUrl = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            number: Cast::string($data['number'] ?? null),
            status: Cast::string($data['status'] ?? null),
            defaultForAppCalls: Cast::bool($data['default_for_app_calls'] ?? null),
            callbackUrl: Cast::string($data['callback_url'] ?? null),
            createdAt: Cast::string($data['created_at'] ?? null),
            updatedAt: Cast::string($data['updated_at'] ?? null),
        );
    }
}
