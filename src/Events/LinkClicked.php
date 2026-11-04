<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `link.clicked` webhook. A tracked short link was followed. Check trafficClass() before counting it as a person. */
final readonly class LinkClicked
{
    public function __construct(public WebhookPayload $payload) {}
}
