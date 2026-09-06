<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

final class ChannelsStateData
{
    /** @param  list<SmsMarketData>  $sms */
    public function __construct(
        public readonly ?string $customerId = null,
        public readonly array $sms = [],
        public readonly ?WhatsappChannelData $whatsapp = null,
        public readonly ?RcsAgentData $rcs = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        $whatsapp = Cast::arr($data['whatsapp'] ?? null);
        $rcs = Cast::arr($data['rcs'] ?? null);

        return new self(
            customerId: Cast::string($data['customer_id'] ?? null),
            sms: array_map(SmsMarketData::fromArray(...), Cast::listOfArrays($data['sms'] ?? null)),
            whatsapp: $whatsapp !== null ? WhatsappChannelData::fromArray($whatsapp) : null,
            rcs: $rcs !== null ? RcsAgentData::fromArray($rcs) : null,
        );
    }
}
