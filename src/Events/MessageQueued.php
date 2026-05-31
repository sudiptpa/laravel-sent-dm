<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `message.queued` webhook. */
final readonly class MessageQueued
{
    public function __construct(public WebhookPayload $payload) {}
}
