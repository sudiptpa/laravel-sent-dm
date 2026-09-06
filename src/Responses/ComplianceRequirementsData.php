<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * `requirements` and `setup` stay `array<string, mixed>`: each requirement's own field
 * list varies by market (a `brand` block's fields aren't a campaign's), there's no fixed
 * shape to type further without duplicating what this endpoint already declares.
 */
final class ComplianceRequirementsData
{
    /**
     * @param  list<array<array-key, mixed>>  $requirements
     * @param  list<array<array-key, mixed>>  $setup
     */
    public function __construct(
        public readonly ?string $channel = null,
        public readonly ?string $country = null,
        public readonly ?string $type = null,
        public readonly ?bool $required = null,
        public readonly ?string $senderIdPattern = null,
        public readonly array $requirements = [],
        public readonly array $setup = [],
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: Cast::string($data['channel'] ?? null),
            country: Cast::string($data['country'] ?? null),
            type: Cast::string($data['type'] ?? null),
            required: Cast::bool($data['required'] ?? null),
            senderIdPattern: Cast::string($data['sender_id_pattern'] ?? null),
            requirements: Cast::listOfArrays($data['requirements'] ?? null),
            setup: Cast::listOfArrays($data['setup'] ?? null),
        );
    }
}
