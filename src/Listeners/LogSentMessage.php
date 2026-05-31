<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use SentDm\Messages\MessageSendResponse;
use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Models\SentLog;

class LogSentMessage
{
    public function handle(MessageSent $event): void
    {
        // Only log from the job context — webhook context has no SentMessage or response.
        if ($event->message === null) {
            return;
        }

        SentLog::create([
            'connection' => $event->connectionName ?? 'default',
            'recipient' => (string) $event->message->getRecipient(),
            'channel' => $event->message->getChannel(),
            'template_name' => $event->message->getTemplateName(),
            'message_id' => $this->extractMessageId($event->response),
            'idempotency_key' => $event->message->getIdempotencyKey(),
            'status' => SentLogStatus::Queued,
            'loggable_type' => $event->message->getLoggableType(),
            'loggable_id' => $event->message->getLoggableId(),
        ]);
    }

    private function extractMessageId(mixed $response): ?string
    {
        if (! $response instanceof MessageSendResponse) {
            return null;
        }

        $recipients = $response->data?->recipients;

        if (! is_array($recipients) || $recipients === []) {
            return null;
        }

        return $recipients[0]->messageID ?? null;
    }
}
