<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

final readonly class UnknownWebhookEvent
{
    public function __construct(public WebhookPayload $payload) {}
}
