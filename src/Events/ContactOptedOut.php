<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `contact.opt_out` webhook. A contact opted out of messages, for example by texting STOP, or a network handled it for them. */
final readonly class ContactOptedOut
{
    public function __construct(public WebhookPayload $payload) {}
}
