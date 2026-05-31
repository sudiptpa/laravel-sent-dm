<?php

declare(strict_types=1);

namespace Sujip\SentDm\Webhooks;

/**
 * Parsed Sent.dm webhook payload.
 *
 * Real structure verified against docs.sent.dm/start/webhooks/event-types:
 *
 *   {
 *     "field": "message",
 *     "sub_type": "message.delivered",
 *     "timestamp": "2025-10-31T10:10:42Z",
 *     "payload": {
 *       "account_id": "...",
 *       "message_id": "...",
 *       "message_status": "DELIVERED",
 *       "channel": "sms",
 *       "inbound_number": "+1234567890",   // recipient
 *       "outbound_number": "+1987654321",  // sender
 *       "template_id": "..."
 *     }
 *   }
 *
 * Inbound (message.received) payload differs:
 *   account_id, from, to, text, channel, provider, received_at
 */
final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $data  the inner "payload" object
     * @param  array<string, mixed>  $raw  the full request body
     */
    public function __construct(
        public string $field,
        public string $subType,
        public ?string $timestamp,
        public array $data,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body): self
    {
        /** @var array<string, mixed> $data */
        $data = is_array($body['payload'] ?? null) ? $body['payload'] : [];

        return new self(
            field: is_string($body['field'] ?? null) ? $body['field'] : '',
            subType: is_string($body['sub_type'] ?? null) ? $body['sub_type'] : '',
            timestamp: is_string($body['timestamp'] ?? null) ? $body['timestamp'] : null,
            data: $data,
            raw: $body,
        );
    }

    public function messageId(): ?string
    {
        return $this->string('message_id');
    }

    public function status(): ?string
    {
        return $this->string('message_status');
    }

    public function channel(): ?string
    {
        return $this->string('channel');
    }

    /** Recipient phone (E.164). Outbound: inbound_number. Inbound: to. */
    public function recipient(): ?string
    {
        return $this->string('inbound_number') ?? $this->string('to');
    }

    /** Sender phone (E.164). Outbound: outbound_number. Inbound: from. */
    public function sender(): ?string
    {
        return $this->string('outbound_number') ?? $this->string('from');
    }

    public function templateId(): ?string
    {
        return $this->string('template_id');
    }

    public function accountId(): ?string
    {
        return $this->string('account_id');
    }

    /** Inbound message text (message.received only). */
    public function text(): ?string
    {
        return $this->string('text');
    }

    /**
     * Deduplication key used by the webhook controller to prevent double-processing.
     *
     * Outbound events: message_id + sub_type (each message transitions to each sub_type at most once).
     * Inbound events (message.received): SHA-256 of the payload body — no message_id is present,
     * but the same inbound message retried by the platform will have identical payload data.
     */
    public function dedupKey(): string
    {
        $messageId = $this->messageId();

        if ($messageId !== null) {
            return "{$messageId}.{$this->subType}";
        }

        return 'inbound.'.hash('sha256', (string) json_encode($this->data));
    }

    private function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
