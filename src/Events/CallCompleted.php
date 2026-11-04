<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `call.completed` webhook. The call ended normally. Carries the duration and, once billed, the price. */
final readonly class CallCompleted
{
    public function __construct(public WebhookPayload $payload) {}
}
