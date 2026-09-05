<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Models\SentLog;

class SyncMessageStatus
{
    public function handle(MessageSent|MessageDelivered|MessageFailed|MessageRead|MessageFiltered|MessageBlocked|MessageScheduled $event): void
    {
        // Only process webhook context. Job context is handled by LogSentMessage.
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
            $event instanceof MessageFiltered => SentLogStatus::Filtered,
            $event instanceof MessageBlocked => SentLogStatus::Blocked,
            $event instanceof MessageScheduled => SentLogStatus::Scheduled,
        };

        // updateOrCreate guards against the race where a webhook arrives before
        // LogSentMessage has created the row. When the row doesn't exist yet,
        // a placeholder is inserted so status is never permanently lost.
        // Recipient and channel are included so the placeholder is queryable.
        SentLog::updateOrCreate(
            ['message_id' => $messageId],
            [
                'status' => $status->value,
                'recipient' => $event->payload->recipient(),
                'channel' => $event->payload->channel(),
            ],
        );
    }
}
