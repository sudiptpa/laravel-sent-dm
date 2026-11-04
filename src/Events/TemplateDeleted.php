<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.deleted` webhook. A template was deleted, including a deletion made outside Sent. */
final readonly class TemplateDeleted
{
    public function __construct(public WebhookPayload $payload) {}
}
