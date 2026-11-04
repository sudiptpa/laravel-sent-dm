<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `call.answered` webhook. The call was picked up. */
final readonly class CallAnswered
{
    public function __construct(public WebhookPayload $payload) {}
}
