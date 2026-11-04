<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `link.downloaded` webhook. A hosted file behind a tracked link was served. */
final readonly class LinkDownloaded
{
    public function __construct(public WebhookPayload $payload) {}
}
