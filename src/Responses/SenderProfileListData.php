<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

final class SenderProfileListData
{
    /**
     * @param  list<SenderProfileData>  $senderProfiles
     * @param  array<array-key, mixed>|null  $pagination
     */
    public function __construct(
        public readonly array $senderProfiles = [],
        public readonly ?array $pagination = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            senderProfiles: array_map(
                SenderProfileData::fromArray(...),
                Cast::listOfArrays($data['sender_profiles'] ?? null),
            ),
            pagination: Cast::arr($data['pagination'] ?? null),
        );
    }
}
