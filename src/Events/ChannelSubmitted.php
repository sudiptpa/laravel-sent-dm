<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.submitted` webhook. A channel registration was filed. */
final readonly class ChannelSubmitted
{
    public function __construct(public WebhookPayload $payload) {}
}
