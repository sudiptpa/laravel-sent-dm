<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.delivered` webhook. */
final readonly class MessageDelivered
{
    public function __construct(public WebhookPayload $payload) {}
}
