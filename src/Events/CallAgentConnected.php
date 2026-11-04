<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `call.agent_connected` webhook. A voice agent joined an answered call (agent-mode calls only). */
final readonly class CallAgentConnected
{
    public function __construct(public WebhookPayload $payload) {}
}
