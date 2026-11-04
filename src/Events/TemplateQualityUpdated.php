<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.quality_updated` webhook. A template quality rating changed. */
final readonly class TemplateQualityUpdated
{
    public function __construct(public WebhookPayload $payload) {}
}
