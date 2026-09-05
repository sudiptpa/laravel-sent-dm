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
        // Only log from the job context. Webhook context has no SentMessage or response.
        if ($event->message === null) {
            return;
        }

        $messageId = $this->extractMessageId($event->response);

        $attributes = [
            'connection' => $event->connectionName ?? 'default',
            'recipient' => (string) $event->message->getRecipient(),
            'channel' => $event->message->getChannel(),
            'template_name' => $event->message->getTemplateName(),
            'idempotency_key' => $event->message->getIdempotencyKey(),
            'loggable_type' => $event->message->getLoggableType(),
            'loggable_id' => $event->message->getLoggableId(),
        ];

        if ($messageId !== null) {
            // Use firstOrCreate so that if SyncMessageStatus already created a placeholder
            // row (race: webhook arrived before this job ran), we fill in the metadata
            // without overwriting the status the webhook already set.
            $log = SentLog::firstOrCreate(
                ['message_id' => $messageId],
                array_merge($attributes, ['status' => SentLogStatus::Queued]),
            );

            if (! $log->wasRecentlyCreated) {
                // Row was created by an early webhook: fill in metadata only, preserve status.
                $log->update($attributes);
            }

            return;
        }

        // No message_id in the response: create a plain row.
        SentLog::create(array_merge($attributes, ['status' => SentLogStatus::Queued]));
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
