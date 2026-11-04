<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.pending` webhook. A template is waiting for review. */
final readonly class TemplatePending
{
    public function __construct(public WebhookPayload $payload) {}
}
