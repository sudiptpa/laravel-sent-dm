<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `link.revoked` webhook. A published link was permanently revoked. */
final readonly class LinkRevoked
{
    public function __construct(public WebhookPayload $payload) {}
}
