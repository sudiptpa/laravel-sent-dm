<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.routed` webhook. */
final readonly class MessageRouted
{
    public function __construct(public WebhookPayload $payload) {}
}
