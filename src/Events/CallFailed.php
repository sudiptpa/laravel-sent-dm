<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `call.failed` webhook. The call did not complete. May carry a machine-readable reason. */
final readonly class CallFailed
{
    public function __construct(public WebhookPayload $payload) {}
}
