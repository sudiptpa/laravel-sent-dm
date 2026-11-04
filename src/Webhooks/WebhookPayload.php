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
     * @param  string|null  $deliveryId  the delivery ID header, stable across retries of one event
     */
    public function __construct(
        public string $field,
        public string $subType,
        public ?string $timestamp,
        public array $data,
        public array $raw,
        public ?string $deliveryId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body, ?string $deliveryId = null): self
    {
        /** @var array<string, mixed> $data */
        $data = is_array($body['payload'] ?? null) ? $body['payload'] : [];

        return new self(
            field: is_string($body['field'] ?? null) ? $body['field'] : '',
            subType: is_string($body['event'] ?? null) ? $body['event'] : '',
            timestamp: is_string($body['timestamp'] ?? null) ? $body['timestamp'] : null,
            data: $data,
            raw: $body,
            deliveryId: $deliveryId,
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

    /** Sent.dm call ID (call.* events), prefixed `call_`. */
    public function callId(): ?string
    {
        return $this->string('call_id');
    }

    /** The customer number that owns the call, in E.164 format (call.* events). */
    public function number(): ?string
    {
        return $this->string('number');
    }

    /** How the call is handled: `inapp` or `agent` (call.* events). */
    public function mode(): ?string
    {
        return $this->string('mode');
    }

    /** Call length in seconds (call.completed only). */
    public function durationSeconds(): ?int
    {
        $value = $this->data['duration_seconds'] ?? null;

        return is_int($value) ? $value : null;
    }

    /** What the call was charged (call.completed only, omitted until billing records it). */
    public function price(): ?float
    {
        $value = $this->data['price'] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /** Recording ID (call.recording_ready only). */
    public function recordingId(): ?string
    {
        return $this->string('recording_id');
    }

    /** Contact the event is about (contact.* events). */
    public function contactId(): ?string
    {
        return $this->string('contact_id');
    }

    /** Whether the contact is opted out after this event (contact.* events). */
    public function optOut(): ?bool
    {
        $value = $this->data['opt_out'] ?? null;

        return is_bool($value) ? $value : null;
    }

    /** What caused a contact event, for example a keyword or a network action (contact.* events). */
    public function source(): ?string
    {
        return $this->string('source');
    }

    /** Sending party of a contact event (contact.* events). */
    public function from(): ?string
    {
        return $this->string('from');
    }

    /** Receiving party of a contact event (contact.* events). */
    public function to(): ?string
    {
        return $this->string('to');
    }

    /** Template category (templates.* events). */
    public function category(): ?string
    {
        return $this->string('category');
    }

    /** Template language (templates.* events). */
    public function language(): ?string
    {
        return $this->string('language');
    }

    /** Country the channel market is for (channel.* events). */
    public function country(): ?string
    {
        return $this->string('country');
    }

    /** Number type of the channel market (channel.* events). */
    public function numberType(): ?string
    {
        return $this->string('number_type');
    }

    /** Sender value of the channel market (channel.* events). */
    public function senderValue(): ?string
    {
        return $this->string('sender_value');
    }

    /** Machine-readable reason a channel needs attention (channel.* events). */
    public function reasonCode(): ?string
    {
        return $this->string('reason_code');
    }

    /**
     * Compliance block of a channel event, as sent.
     *
     * @return array<string, mixed>|null
     */
    public function compliance(): ?array
    {
        $value = $this->data['compliance'] ?? null;

        /** @var array<string, mixed>|null */
        return is_array($value) ? $value : null;
    }

    /** Customer the link belongs to (link.* events). */
    public function customerId(): ?string
    {
        return $this->string('customer_id');
    }

    /** Sender profile the link was sent as (link.* events). */
    public function senderProfileId(): ?string
    {
        return $this->string('sender_profile_id');
    }

    /** Link record ID (link.* events). */
    public function recordId(): ?string
    {
        return $this->string('record_id');
    }

    /** Kind of link (link.* events). */
    public function linkKind(): ?string
    {
        return $this->string('link_kind');
    }

    /** Your own reference key for the link (link.* events). */
    public function referenceKey(): ?string
    {
        return $this->string('reference_key');
    }

    /** When the link event happened (link.* events). */
    public function occurredAt(): ?string
    {
        return $this->string('occurred_at');
    }

    /** HTTP method of the request (link.clicked and link.downloaded only). */
    public function requestMethod(): ?string
    {
        return $this->string('request_method');
    }

    /** HTTP status served (link.clicked and link.downloaded only). */
    public function statusCode(): ?int
    {
        $value = $this->data['status_code'] ?? null;

        return is_int($value) ? $value : null;
    }

    /** Whether the request looks like a person, a bot or a scanner (link access events only). */
    public function trafficClass(): ?string
    {
        return $this->string('traffic_class');
    }

    /** Country the request came from (link access events only). */
    public function accessCountry(): ?string
    {
        return $this->string('access_country');
    }

    /** Device of the request (link access events only). */
    public function device(): ?string
    {
        return $this->string('device');
    }

    /** Browser of the request (link access events only). */
    public function browser(): ?string
    {
        return $this->string('browser');
    }

    /** Host the request was referred from (link access events only). */
    public function referrerHost(): ?string
    {
        return $this->string('referrer_host');
    }

    /** Bytes served (link access events only). */
    public function bytesServed(): ?int
    {
        $value = $this->data['bytes_served'] ?? null;

        return is_int($value) ? $value : null;
    }

    /** Outcome of the access (link access events only). */
    public function accessOutcome(): ?string
    {
        return $this->string('access_outcome');
    }

    /**
     * Deduplication key used by the webhook controller to prevent double-processing.
     *
     * Outbound events: message_id + event type (each message transitions to each event type at most once).
     * Call events: call_id + event type, plus the recording ID because one call can have several recordings.
     * Other non-message events (contact, templates, channel, link): the delivery ID header when present,
     * which Sent keeps the same across retries, otherwise a hash of the event type, timestamp and payload.
     * Older inbound payloads without a message_id use a SHA-256 content hash.
     */
    public function dedupKey(): string
    {
        $callId = $this->callId();

        if ($callId !== null && str_starts_with($this->subType, 'call.')) {
            $recordingId = $this->recordingId();

            return $recordingId !== null
                ? "{$callId}.{$this->subType}.{$recordingId}"
                : "{$callId}.{$this->subType}";
        }

        if ($this->field !== '' && $this->field !== 'message') {
            return $this->deliveryId !== null
                ? "delivery.{$this->deliveryId}"
                : "{$this->subType}.".hash('sha256', (string) json_encode([$this->timestamp, $this->data]));
        }

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
