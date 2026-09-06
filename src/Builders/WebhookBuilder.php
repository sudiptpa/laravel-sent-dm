<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use InvalidArgumentException;
use SentDm\Client;
use SentDm\Webhooks\WebhookNewResponse;
use SentDm\Webhooks\WebhookUpdateResponse;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;

class WebhookBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $name = null;

    private ?string $url = null;

    /** @var list<string> */
    private array $events = [];

    /** @var array<string, list<string>>|null */
    private ?array $eventFilters = null;

    private ?int $retryCount = null;

    private ?int $timeoutSeconds = null;

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

    /**
     * Required on both create and update, like name(). The spec doesn't list it in
     * `required`, but a missing or invalid URL fails live with "Endpoint URL must be a
     * valid HTTP or HTTPS URL".
     */
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
     * Restricts delivery to specific sub-types per category, e.g. `['message' =>
     * ['delivered', 'failed']]` to skip queued/routed/sent/etc. Omit to receive every
     * sub-type of a subscribed category.
     *
     * @param  array<string, list<string>>  $eventFilters
     */
    public function eventFilters(array $eventFilters): static
    {
        $clone = clone $this;
        $clone->eventFilters = $eventFilters;

        return $clone;
    }

    /** 1-5. */
    public function retryCount(int $retryCount): static
    {
        $clone = clone $this;
        $clone->retryCount = $retryCount;

        return $clone;
    }

    /** 5-120. */
    public function timeoutSeconds(int $timeoutSeconds): static
    {
        $clone = clone $this;
        $clone->timeoutSeconds = $timeoutSeconds;

        return $clone;
    }

    public function save(): WebhookNewResponse|WebhookUpdateResponse
    {
        if ($this->name === null) {
            throw new InvalidArgumentException('A name is required. Call name() before save().');
        }

        if ($this->url === null) {
            throw new InvalidArgumentException('A URL is required. Call url() before save().');
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
                eventFilters: $this->eventFilters,
                retryCount: $this->retryCount,
                timeoutSeconds: $this->timeoutSeconds,
                sandbox: $sandbox,
                idempotencyKey: $this->idempotencyKey,
                xProfileID: $this->profileId,
            );
        }

        return $this->client->webhooks->create(
            displayName: $this->name,
            endpointURL: $this->url,
            eventTypes: $this->events,
            eventFilters: $this->eventFilters,
            retryCount: $this->retryCount,
            timeoutSeconds: $this->timeoutSeconds,
            sandbox: $sandbox,
            idempotencyKey: $this->idempotencyKey,
            xProfileID: $this->profileId,
        );
    }
}
