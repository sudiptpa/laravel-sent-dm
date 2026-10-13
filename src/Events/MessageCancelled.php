<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.cancelled` webhook. A scheduled message was called off before release. */
final readonly class MessageCancelled
{
    public function __construct(public WebhookPayload $payload) {}
}
