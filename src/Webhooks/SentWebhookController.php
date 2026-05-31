<?php

declare(strict_types=1);

namespace Sujip\SentDm\Webhooks;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sujip\SentDm\Events\MessageDelivered;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageQueued;
use Sujip\SentDm\Events\MessageRead;
use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Events\MessageRouted;
use Sujip\SentDm\Events\MessageSent;

class SentWebhookController
{
    public function __construct(private readonly Cache $cache) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = (array) (json_decode($request->getContent(), true) ?? []);

        $payload = WebhookPayload::fromArray($body);

        $dedupKey = $payload->dedupKey();
        $cacheKey = $dedupKey !== null ? "sent.webhook.event.{$dedupKey}" : null;

        if ($cacheKey !== null && ! $this->cache->add($cacheKey, true, 86400)) {
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
                'message.received' => event(new MessageReceived($payload)),
                default => null,
            };
        } catch (\Throwable $e) {
            if ($cacheKey !== null) {
                $this->cache->forget($cacheKey);
            }

            throw $e;
        }

        return response()->json(['message' => 'OK']);
    }
}
