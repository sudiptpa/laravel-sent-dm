<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.received` webhook: an inbound message from a contact. */
final readonly class MessageReceived
{
    public function __construct(public WebhookPayload $payload) {}
}
