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
 *     "event": "message.delivered",
 *     "timestamp": "2025-10-31T10:10:42Z",
 *     "payload": {
 *       "account_id": "...",
 *       "message_id": "...",
 *       "message_status": "DELIVERED",
 *       "channel": "sms",
 *       "outbound_number": "+1234567890",  // recipient
 *       "template_id": "..."
 *     }
 *   }
 *
 * Inbound (message.received) payload differs:
 *   inbound_number is the contact; outbound_number is the receiving number.
 *   Legacy from/to payloads are also supported.
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
            subType: is_string($body['event'] ?? null) ? $body['event'] : '',
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
        return $this->string('message_status') ?? $this->string('status');
    }

    public function channel(): ?string
    {
        return $this->string('channel');
    }

    /** Recipient phone number in E.164 format. */
    public function recipient(): ?string
    {
        if ($this->subType === 'message.received') {
            return $this->string('to') ?? $this->string('outbound_number');
        }

        return $this->string('inbound_number') ?? $this->string('outbound_number') ?? $this->string('to');
    }

    /** Sender phone number, when included in the payload. */
    public function sender(): ?string
    {
        if ($this->subType === 'message.received') {
            return $this->string('from') ?? $this->string('inbound_number');
        }

        return $this->string('inbound_number') !== null
            ? $this->string('outbound_number')
            : $this->string('from');
    }

    public function templateId(): ?string
    {
        return $this->string('template_id');
    }

    public function templateName(): ?string
    {
        return $this->string('template_name');
    }

    public function whatsappTemplateId(): ?string
    {
        return $this->string('whatsapp_template_id');
    }

    public function requestId(): ?string
    {
        return $this->rawString('request_id') ?? $this->string('request_id');
    }

    public function body(): ?string
    {
        return $this->string('body');
    }

    public function updatedAt(): ?string
    {
        return $this->string('updated_at');
    }

    public function agentId(): ?string
    {
        return $this->string('agent_id');
    }

    public function scheduledAt(): ?string
    {
        return $this->string('scheduled_at');
    }

    public function scheduleReason(): ?string
    {
        return $this->string('schedule_reason');
    }

    public function reason(): ?string
    {
        return $this->string('reason');
    }

    public function autoReplyAction(): ?string
    {
        return $this->string('auto_reply_action');
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
     * Outbound events: message_id + event type (each message transitions to each event type at most once).
     * Older inbound payloads without a message_id use a SHA-256 content hash.
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

    private function rawString(string $key): ?string
    {
        $value = $this->raw[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
