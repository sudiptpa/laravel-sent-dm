<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.changes_requested` webhook. The channel is waiting on you. Nothing progresses until you act. */
final readonly class ChannelChangesRequested
{
    public function __construct(public WebhookPayload $payload) {}
}
