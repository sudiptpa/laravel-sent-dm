<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `channel.approved` webhook. The verdict is in and Sent is acquiring and binding the sender. Not live yet. */
final readonly class ChannelApproved
{
    public function __construct(public WebhookPayload $payload) {}
}
