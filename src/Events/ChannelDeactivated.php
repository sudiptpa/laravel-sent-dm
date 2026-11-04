<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.deactivated` webhook. The channel had a working sender and no longer does. */
final readonly class ChannelDeactivated
{
    public function __construct(public WebhookPayload $payload) {}
}
