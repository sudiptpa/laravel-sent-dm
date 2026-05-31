<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

final readonly class MessageSent
{
    public function __construct(
        public ?SentMessage $message,
        public mixed $response,
        public ?WebhookPayload $payload = null,
        public ?string $connectionName = null,
    ) {}

    public static function fromWebhook(WebhookPayload $payload): self
    {
        return new self(message: null, response: null, payload: $payload);
    }
}
