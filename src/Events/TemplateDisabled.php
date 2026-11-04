<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.disabled` webhook. A template was disabled, for example for a policy violation. */
final readonly class TemplateDisabled
{
    public function __construct(public WebhookPayload $payload) {}
}
