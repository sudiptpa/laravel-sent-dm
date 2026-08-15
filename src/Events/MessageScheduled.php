<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.scheduled` webhook. The send was deferred to a later window (quiet hours or a scheduled send) and is not yet final. */
final readonly class MessageScheduled
{
    public function __construct(public WebhookPayload $payload) {}
}
