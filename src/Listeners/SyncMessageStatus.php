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

        $attributes = [
            'status' => $status->value,
            'recipient' => $event->payload->recipient(),
            'channel' => $event->payload->channel(),
        ];

        // Preserve status when a webhook arrives before the send job logs its response.
        $log = SentLog::firstOrCreate(
            ['message_id' => $messageId],
            $attributes,
        );

        // Check the stored status in the UPDATE so another handler cannot advance
        // the row between a PHP status check and the write.
        // https://docs.sent.dm/build/status-tracking
        $previousStatuses = match ($status) {
            SentLogStatus::Scheduled => [SentLogStatus::Queued],
            SentLogStatus::Sent, SentLogStatus::Filtered, SentLogStatus::Blocked => [SentLogStatus::Queued, SentLogStatus::Scheduled],
            SentLogStatus::Delivered, SentLogStatus::Failed => [SentLogStatus::Queued, SentLogStatus::Scheduled, SentLogStatus::Sent],
            SentLogStatus::Read => [SentLogStatus::Queued, SentLogStatus::Scheduled, SentLogStatus::Sent, SentLogStatus::Delivered],
        };

        SentLog::whereKey($log->getKey())
            ->whereIn('status', $previousStatuses)
            ->update($attributes);
    }
}
