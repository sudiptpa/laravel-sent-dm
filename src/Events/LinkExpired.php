<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `link.expired` webhook. A published link reached its expiration. */
final readonly class LinkExpired
{
    public function __construct(public WebhookPayload $payload) {}
}
