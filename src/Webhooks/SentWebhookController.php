<?php

declare(strict_types=1);

namespace Sujip\SentDm\Webhooks;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Sujip\SentDm\Events\MessageBlocked;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageFiltered;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageScheduled;
use Sujip\SentDm\Events\MessageSent;

class SentWebhookController
{
    public function __construct(
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = (array) (json_decode($request->getContent(), true) ?? []);

        $payload = WebhookPayload::fromArray($body);

        $cacheKey = 'sent.webhook.event.'.$payload->dedupKey();

        if (! $this->cache->add($cacheKey, true, 86400)) {
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
                'message.received' => event(new MessageReceived($payload)),
                default => $this->logger->warning('sent: unrecognized webhook event type', [
                    'event' => $payload->subType,
                    'message_id' => $payload->messageId(),
                ]),
            };
        } catch (\Throwable $e) {
            $this->cache->forget($cacheKey);

            throw $e;
        }

        return response()->json(['message' => 'OK']);
    }
}
