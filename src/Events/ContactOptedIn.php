<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `contact.opt_in` webhook. A contact opted back in to messages, for example by texting START. */
final readonly class ContactOptedIn
{
    public function __construct(public WebhookPayload $payload) {}
}
