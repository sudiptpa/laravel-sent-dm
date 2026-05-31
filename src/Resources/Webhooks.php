<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Webhooks\APIResponseWebhook;
use SentDm\Webhooks\WebhookListEventsResponse;
use SentDm\Webhooks\WebhookListEventTypesResponse;
use SentDm\Webhooks\WebhookListResponse;
use SentDm\Webhooks\WebhookTestResponse;
use Sujip\SentDm\Builders\WebhookBuilder;

class Webhooks extends Resource
{
    private int $page = 1;

    private int $pageSize = 50;

    public function page(int $page): static
    {
        $clone = clone $this;
        $clone->page = $page;

        return $clone;
    }

    public function perPage(int $perPage): static
    {
        $clone = clone $this;
        $clone->pageSize = $perPage;

        return $clone;
    }

    public function get(): WebhookListResponse
    {
        return $this->client->webhooks->list(page: $this->page, pageSize: $this->pageSize);
    }

    public function find(string $id): APIResponseWebhook
    {
        return $this->client->webhooks->retrieve(id: $id);
    }

    public function create(): WebhookBuilder
    {
        return new WebhookBuilder(client: $this->client);
    }

    public function update(string $id): WebhookBuilder
    {
        return new WebhookBuilder(client: $this->client, id: $id);
    }

    public function delete(string $id): void
    {
        $this->client->webhooks->delete(id: $id);
    }

    public function enable(string $id): APIResponseWebhook
    {
        return $this->client->webhooks->toggleStatus(id: $id, isActive: true);
    }

    public function disable(string $id): APIResponseWebhook
    {
        return $this->client->webhooks->toggleStatus(id: $id, isActive: false);
    }

    public function rotateSecret(string $id): mixed
    {
        return $this->client->webhooks->rotateSecret(id: $id, body: []);
    }

    public function test(string $id, ?string $eventType = null): WebhookTestResponse
    {
        return $this->client->webhooks->test(id: $id, eventType: $eventType);
    }

    public function listEvents(string $id, int $page = 1, int $pageSize = 50): WebhookListEventsResponse
    {
        return $this->client->webhooks->listEvents(id: $id, page: $page, pageSize: $pageSize);
    }

    public function listEventTypes(): WebhookListEventTypesResponse
    {
        return $this->cached(
            'sent.webhook.event-types',
            fn () => $this->client->webhooks->listEventTypes(),
        );
    }
}
