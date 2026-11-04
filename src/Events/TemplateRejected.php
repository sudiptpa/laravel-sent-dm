<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.rejected` webhook. A template was rejected in review. */
final readonly class TemplateRejected
{
    public function __construct(public WebhookPayload $payload) {}
}
