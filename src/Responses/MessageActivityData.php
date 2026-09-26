<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * Fields from the live spec's MessageActivityResponse schema. Built from the raw
 * response, not `SentDm\Messages\MessageGetActivitiesResponse\Data\Activity`, because
 * that generated class has no `scheduled_at` property even though the spec declares
 * it (SCHEDULED activities only). Revert to the generated class, and drop this file
 * and MessageActivitiesData, once a `sentdm/sent-dm-php` release adds the field.
 */
final class MessageActivityData
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $description = null,
        public readonly ?string $from = null,
        public readonly ?string $timestamp = null,
        public readonly ?string $scheduledAt = null,
        public readonly ?string $price = null,
        public readonly ?string $activeContactPrice = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Cast::string($data['status'] ?? null),
            description: Cast::string($data['description'] ?? null),
            from: Cast::string($data['from'] ?? null),
            timestamp: Cast::string($data['timestamp'] ?? null),
            scheduledAt: Cast::string($data['scheduled_at'] ?? null),
            price: Cast::string($data['price'] ?? null),
            activeContactPrice: Cast::string($data['active_contact_price'] ?? null),
        );
    }
}
