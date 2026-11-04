<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.approved` webhook. A template was approved and can be sent. */
final readonly class TemplateApproved
{
    public function __construct(public WebhookPayload $payload) {}
}
