<?php

declare(strict_types=1);

namespace Sujip\SentDm\Webhooks;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Sujip\SentDm\Events\CallAgentConnected;
use Sujip\SentDm\Events\CallAnswered;
use Sujip\SentDm\Events\CallCompleted;
use Sujip\SentDm\Events\CallFailed;
use Sujip\SentDm\Events\CallInitiated;
use Sujip\SentDm\Events\CallRecordingReady;
use Sujip\SentDm\Events\ChannelActivated;
use Sujip\SentDm\Events\ChannelApproved;
use Sujip\SentDm\Events\ChannelChangesRequested;
use Sujip\SentDm\Events\ChannelDeactivated;
use Sujip\SentDm\Events\ChannelRejected;
use Sujip\SentDm\Events\ChannelRemoved;
use Sujip\SentDm\Events\ChannelSubmitted;
use Sujip\SentDm\Events\ContactCustomKeyword;
use Sujip\SentDm\Events\ContactHelpRequested;
use Sujip\SentDm\Events\ContactOptedIn;
use Sujip\SentDm\Events\ContactOptedOut;
use Sujip\SentDm\Events\LinkClicked;
use Sujip\SentDm\Events\LinkDownloaded;
use Sujip\SentDm\Events\LinkExpired;
use Sujip\SentDm\Events\LinkRevoked;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageCancelled;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Events\TemplateApproved;
use Sujip\SentDm\Events\TemplateCategoryUpdated;
use Sujip\SentDm\Events\TemplateDeleted;
use Sujip\SentDm\Events\TemplateDisabled;
use Sujip\SentDm\Events\TemplatePaused;
use Sujip\SentDm\Events\TemplatePending;
use Sujip\SentDm\Events\TemplateQualityUpdated;
use Sujip\SentDm\Events\TemplateRejected;
use Sujip\SentDm\Events\UnknownWebhookEvent;

class SentWebhookController
{
    /**
     * Non-message webhook events, by the event type Sent sends.
     *
     * @var array<string, class-string>
     */
    private const EVENTS = [
        'call.initiated' => CallInitiated::class,
        'call.answered' => CallAnswered::class,
        'call.agent_connected' => CallAgentConnected::class,
        'call.completed' => CallCompleted::class,
        'call.failed' => CallFailed::class,
        'call.recording_ready' => CallRecordingReady::class,
        'contact.opt_in' => ContactOptedIn::class,
        'contact.opt_out' => ContactOptedOut::class,
        'contact.help' => ContactHelpRequested::class,
        'contact.custom_keyword' => ContactCustomKeyword::class,
        'templates.approved' => TemplateApproved::class,
        'templates.rejected' => TemplateRejected::class,
        'templates.pending' => TemplatePending::class,
        'templates.deleted' => TemplateDeleted::class,
        'templates.category_updated' => TemplateCategoryUpdated::class,
        'templates.quality_updated' => TemplateQualityUpdated::class,
        'templates.paused' => TemplatePaused::class,
        'templates.disabled' => TemplateDisabled::class,
        'link.clicked' => LinkClicked::class,
        'link.downloaded' => LinkDownloaded::class,
        'link.expired' => LinkExpired::class,
        'link.revoked' => LinkRevoked::class,
        'channel.submitted' => ChannelSubmitted::class,
        'channel.changes_requested' => ChannelChangesRequested::class,
        'channel.approved' => ChannelApproved::class,
        'channel.activated' => ChannelActivated::class,
        'channel.deactivated' => ChannelDeactivated::class,
        'channel.rejected' => ChannelRejected::class,
        'channel.removed' => ChannelRemoved::class,
    ];

    public function __construct(
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = (array) (json_decode($request->getContent(), true) ?? []);

        $payload = WebhookPayload::fromArray($body, $this->deliveryId($request));

        $cacheKey = 'sent.webhook.event.'.$payload->dedupKey();

        if (! $this->cache->add($cacheKey, true, $this->dedupTtl())) {
            return response()->json(['message' => 'OK']);
        }

        try {
            match ($payload->subType) {
                'message.queued' => event(new MessageQueued($payload)),
                'message.routed' => event(new MessageRouted($payload)),
                'message.sent' => event(MessageSent::fromWebhook($payload)),
                'message.delivered' => event(new MessageDelivered($payload)),
                'message.read' => event(new MessageRead($payload)),
                'message.failed' => event(MessageFailed::fromWebhook($payload)),
                'message.filtered' => event(new MessageFiltered($payload)),
                'message.blocked' => event(new MessageBlocked($payload)),
                'message.scheduled' => event(new MessageScheduled($payload)),
                'message.cancelled' => event(new MessageCancelled($payload)),
                'message.received' => event(new MessageReceived($payload)),
                default => $this->handleOtherEvent($payload),
            };
        } catch (\Throwable $e) {
            $this->cache->forget($cacheKey);

            throw $e;
        }

        return response()->json(['message' => 'OK']);
    }

    private function handleOtherEvent(WebhookPayload $payload): void
    {
        $class = self::EVENTS[$payload->subType] ?? null;

        if ($class !== null) {
            event(new $class($payload));

            return;
        }

        $this->handleUnknownEvent($payload);
    }

    private function handleUnknownEvent(WebhookPayload $payload): void
    {
        $this->logger->warning('sent: unrecognized webhook event type', [
            'event' => $payload->subType,
            'message_id' => $payload->messageId(),
        ]);

        event(new UnknownWebhookEvent($payload));
    }

    private function deliveryId(Request $request): ?string
    {
        foreach (['webhook-id', 'x-webhook-event-id'] as $header) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function dedupTtl(): int
    {
        $ttl = config('sent.webhook.dedup_ttl', 86400);

        return is_numeric($ttl) ? max(1, (int) $ttl) : 86400;
    }
}
