<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * See MessageActivityData for why this exists instead of the generated
 * `SentDm\Messages\MessageGetActivitiesResponse`.
 */
final class MessageActivitiesData
{
    /** @param  list<MessageActivityData>  $activities */
    public function __construct(
        public readonly ?string $messageId = null,
        public readonly array $activities = [],
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: Cast::string($data['message_id'] ?? null),
            activities: array_map(MessageActivityData::fromArray(...), Cast::listOfArrays($data['activities'] ?? null)),
        );
    }
}
