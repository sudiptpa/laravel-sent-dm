<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.filtered` webhook. The send was suppressed by a routing or consent rule before reaching a carrier. */
final readonly class MessageFiltered
{
    public function __construct(public WebhookPayload $payload) {}
}
