<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.removed` webhook. The channel was removed. */
final readonly class ChannelRemoved
{
    public function __construct(public WebhookPayload $payload) {}
}
