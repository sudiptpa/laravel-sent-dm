<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use SentDm\Client;
use SentDm\Webhooks\WebhookNewResponse;
use SentDm\Webhooks\WebhookUpdateResponse;

class WebhookBuilder
{
    private ?string $url = null;

    /** @var list<string> */
    private array $events = [];

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
    ) {}

    public function url(string $url): static
    {
        $clone = clone $this;
        $clone->url = $url;

        return $clone;
    }

    /** @param list<string> $events */
    public function events(array $events): static
    {
        $clone = clone $this;
        $clone->events = $events;

        return $clone;
    }

    public function save(): WebhookNewResponse|WebhookUpdateResponse
    {
        if ($this->id !== null) {
            return $this->client->webhooks->update(
                id: $this->id,
                endpointURL: $this->url,
                eventTypes: $this->events !== [] ? $this->events : null,
            );
        }

        return $this->client->webhooks->create(
            endpointURL: $this->url,
            eventTypes: $this->events !== [] ? $this->events : null,
        );
    }
}
