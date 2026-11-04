<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `contact.help` webhook. A contact asked for help, for example by texting HELP. No consent change. */
final readonly class ContactHelpRequested
{
    public function __construct(public WebhookPayload $payload) {}
}
