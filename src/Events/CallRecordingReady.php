<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `call.recording_ready` webhook. A call recording is available. Sent once per recording. */
final readonly class CallRecordingReady
{
    public function __construct(public WebhookPayload $payload) {}
}
