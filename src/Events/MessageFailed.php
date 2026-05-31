<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

final readonly class MessageFailed
{
    public function __construct(
        public ?SentMessage $message,
        public ?\Throwable $exception,
        public ?WebhookPayload $payload = null,
    ) {}

    public static function fromWebhook(WebhookPayload $payload): self
    {
        return new self(message: null, exception: null, payload: $payload);
    }
}
