<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.read` webhook (WhatsApp/RCS only). */
final readonly class MessageRead
{
    public function __construct(public WebhookPayload $payload) {}
}
