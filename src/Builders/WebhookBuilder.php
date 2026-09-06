<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use InvalidArgumentException;
use SentDm\Client;
use SentDm\Webhooks\WebhookNewResponse;
use SentDm\Webhooks\WebhookUpdateResponse;

class WebhookBuilder
{
    private ?string $name = null;

    private ?string $url = null;

    /** @var list<string> */
    private array $events = [];

    private ?bool $sandbox = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
        private readonly ?string $profileId = null,
        private readonly bool $sandboxDefault = false,
    ) {}

    /**
     * Required on both create and update. Omitting it fails live with "Display name is
     * required", even though the SDK types the field as optional.
     */
    public function name(string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function url(string $url): static
    {
        $clone = clone $this;
        $clone->url = $url;

        return $clone;
    }

    /**
     * Top-level categories to subscribe to, not the granular event names delivered in the
     * webhook payload. `["message.sent"]` fails with a 400 ("Invalid event type... Allowed
     * types: message, templates").
     * Subscribe to `["message"]` to receive all ten message.* sub-events (queued, routed,
     * sent, delivered, read, failed, filtered, blocked, scheduled, received), the payload's
     * own `event` field is what carries the granular name once delivered. See
     * `Webhooks::listEventTypes()` for the authoritative category/sub-type list.
     *
     * @param  list<string>  $events
     */
    public function events(array $events): static
    {
        $clone = clone $this;
        $clone->events = $events;

        return $clone;
    }

    /**
     * Explicit here always wins over the global SENT_SANDBOX config, same as
     * SentMessage::sandbox(). Call sandbox(false) to force a real save even when
     * SENT_SANDBOX=true is set.
     */
    public function sandbox(bool $sandbox = true): static
    {
        $clone = clone $this;
        $clone->sandbox = $sandbox;

        return $clone;
    }

    public function save(): WebhookNewResponse|WebhookUpdateResponse
    {
        if ($this->name === null) {
            throw new InvalidArgumentException('A name is required. Call name() before save().');
        }

        if ($this->events === []) {
            throw new InvalidArgumentException('At least one event category is required. Call events() before save().');
        }

        $sandbox = ($this->sandbox ?? $this->sandboxDefault) ?: null;

        if ($this->id !== null) {
            return $this->client->webhooks->update(
                id: $this->id,
                displayName: $this->name,
                endpointURL: $this->url,
                eventTypes: $this->events,
                sandbox: $sandbox,
                xProfileID: $this->profileId,
            );
        }

        return $this->client->webhooks->create(
            displayName: $this->name,
            endpointURL: $this->url,
            eventTypes: $this->events,
            sandbox: $sandbox,
            xProfileID: $this->profileId,
        );
    }
}
