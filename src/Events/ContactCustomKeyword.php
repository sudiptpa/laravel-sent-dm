<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `contact.custom_keyword` webhook. A contact sent one of your own auto-reply keywords. No consent change. */
final readonly class ContactCustomKeyword
{
    public function __construct(public WebhookPayload $payload) {}
}
