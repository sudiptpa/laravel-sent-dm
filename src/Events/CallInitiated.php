<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `call.initiated` webhook. A call was created and is ringing or being set up. */
final readonly class CallInitiated
{
    public function __construct(public WebhookPayload $payload) {}
}
