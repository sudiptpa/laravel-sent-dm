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

        $sharedAttributes = [
            'connection' => $event->connectionName ?? 'default',
            'template_name' => $event->message->getTemplateName(),
            'idempotency_key' => $event->message->getIdempotencyKey(),
            'loggable_type' => $event->message->getLoggableType(),
            'loggable_id' => $event->message->getLoggableId(),
        ];

        $recipients = $this->extractRecipients($event->response);

        if ($recipients === []) {
            // No per-recipient detail in the response: one row from the message itself.
            SentLog::create(array_merge($sharedAttributes, [
                'recipient' => (string) $event->message->getRecipient(),
                'channel' => $event->message->getChannel(),
                'status' => SentLogStatus::Queued,
            ]));

            return;
        }

        // Multi-channel fans out to one message per (recipient, channel) pair, so the
        // response can carry more than one entry even for a single-recipient send. Log
        // every one, not just the first, or every entry past the first is silently lost.
        foreach ($recipients as $recipient) {
            $attributes = array_merge($sharedAttributes, [
                'recipient' => (string) ($recipient->to ?? $event->message->getRecipient()),
                'channel' => $recipient->channel ?? $event->message->getChannel(),
            ]);

            $messageId = $recipient->messageID ?? null;

            if ($messageId === null) {
                SentLog::create(array_merge($attributes, ['status' => SentLogStatus::Queued]));

                continue;
            }

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
        }
    }

    /** @return list<object{to?: ?string, channel?: ?string, messageID?: ?string}> */
    private function extractRecipients(mixed $response): array
    {
        if (! $response instanceof MessageSendResponse) {
            return [];
        }

        $recipients = $response->data?->recipients;

        return is_array($recipients) ? $recipients : [];
    }
}
