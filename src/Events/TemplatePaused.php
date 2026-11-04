<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.paused` webhook. A template was paused for low quality. */
final readonly class TemplatePaused
{
    public function __construct(public WebhookPayload $payload) {}
}
