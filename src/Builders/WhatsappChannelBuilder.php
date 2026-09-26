<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use InvalidArgumentException;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Responses\WhatsappChannelData;

/**
 * Fluent alternative to `Channels::addWhatsapp(array $data)`, which stays as-is.
 * `POST /v3/channels/whatsapp` has no update endpoint, so this is create-only.
 */
class WhatsappChannelBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $wabaId = null;

    private ?string $phoneNumberId = null;

    public function __construct(
        private readonly Channels $resource,
    ) {}

    /**
     * The organization's own WhatsApp Business Account id. Every other field
     * (access token, expiry, business portfolio) is resolved from the organization.
     */
    public function wabaId(string $wabaId): static
    {
        $clone = clone $this;
        $clone->wabaId = $wabaId;

        return $clone;
    }

    public function phoneNumberId(string $phoneNumberId): static
    {
        $clone = clone $this;
        $clone->phoneNumberId = $phoneNumberId;

        return $clone;
    }

    public function save(): WhatsappChannelData
    {
        if ($this->wabaId === null) {
            throw new InvalidArgumentException('A WABA id is required. Call wabaId() before save().');
        }

        $data = array_filter([
            'waba_id' => $this->wabaId,
            'phone_number_id' => $this->phoneNumberId,
            'sandbox' => $this->sandbox,
        ], fn (mixed $value): bool => $value !== null);

        return $this->resource->addWhatsapp($data, $this->idempotencyKey);
    }
}
