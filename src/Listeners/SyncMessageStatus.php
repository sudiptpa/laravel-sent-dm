<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Models\SentLog;

class SyncMessageStatus
{
    public function handle(MessageSent|MessageDelivered|MessageFailed|MessageRead $event): void
    {
        // Only process webhook context — job context is handled by LogSentMessage.
        if ($event->payload === null) {
            return;
        }

        $messageId = $event->payload->messageId();

        if ($messageId === null) {
            return;
        }

        $status = match (true) {
            $event instanceof MessageSent => SentLogStatus::Sent,
            $event instanceof MessageDelivered => SentLogStatus::Delivered,
            $event instanceof MessageFailed => SentLogStatus::Failed,
            $event instanceof MessageRead => SentLogStatus::Read,
        };

        SentLog::where('message_id', $messageId)->update(['status' => $status->value]);
    }
}
