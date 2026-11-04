<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.rejected` webhook. The channel registration was rejected. */
final readonly class ChannelRejected
{
    public function __construct(public WebhookPayload $payload) {}
}
