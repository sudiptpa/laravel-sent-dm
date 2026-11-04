<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.activated` webhook. The channel can carry traffic now. */
final readonly class ChannelActivated
{
    public function __construct(public WebhookPayload $payload) {}
}
