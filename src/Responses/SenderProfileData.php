<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * Fields taken from the live OpenAPI spec's SenderProfileResponse schema, not guessed.
 * `billing`/`channels`/`compliance` stay `array<string, mixed>`: Sent.dm's own docs say
 * their shape varies per market/capability, there's nothing fixed to type further.
 */
final class SenderProfileData
{
    /**
     * @param  array<array-key, mixed>|null  $billing
     * @param  array<array-key, mixed>|null  $channels
     * @param  array<array-key, mixed>|null  $compliance
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $name = null,
        public readonly ?string $shortName = null,
        public readonly ?string $description = null,
        public readonly ?string $apiKey = null,
        public readonly ?array $billing = null,
        public readonly ?array $channels = null,
        public readonly ?array $compliance = null,
        public readonly ?string $createdAt = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Cast::string($data['id'] ?? null),
            organizationId: Cast::string($data['organization_id'] ?? null),
            name: Cast::string($data['name'] ?? null),
            shortName: Cast::string($data['short_name'] ?? null),
            description: Cast::string($data['description'] ?? null),
            apiKey: Cast::string($data['api_key'] ?? null),
            billing: Cast::arr($data['billing'] ?? null),
            channels: Cast::arr($data['channels'] ?? null),
            compliance: Cast::arr($data['compliance'] ?? null),
            createdAt: Cast::string($data['created_at'] ?? null),
        );
    }
}
