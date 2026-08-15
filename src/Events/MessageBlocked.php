<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.blocked` webhook. The send was gated by an account condition, an unapproved template, or no open conversation. */
final readonly class MessageBlocked
{
    public function __construct(public WebhookPayload $payload) {}
}
