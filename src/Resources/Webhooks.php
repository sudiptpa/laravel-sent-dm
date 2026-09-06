<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use InvalidArgumentException;
use SentDm\Webhooks\WebhookGetResponse;
use SentDm\Webhooks\WebhookListEventsResponse;
use SentDm\Webhooks\WebhookListEventTypesResponse;
use SentDm\Webhooks\WebhookListResponse;
use SentDm\Webhooks\WebhookRotateSecretResponse;
use SentDm\Webhooks\WebhookTestResponse;
use SentDm\Webhooks\WebhookToggleStatusResponse;
use Sujip\SentDm\Builders\WebhookBuilder;

class Webhooks extends Resource
{
    private int $page = 1;

    private int $pageSize = 50;

    private ?string $search = null;

    private ?bool $isActive = null;

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

    public function search(string $search): static
    {
        $clone = clone $this;
        $clone->search = $search;

        return $clone;
    }

    public function isActive(bool $isActive = true): static
    {
        $clone = clone $this;
        $clone->isActive = $isActive;

        return $clone;
    }

    public function get(): WebhookListResponse
    {
        return $this->client->webhooks->list(
            page: $this->page,
            pageSize: $this->pageSize,
            search: $this->search,
            isActive: $this->isActive,
            xProfileID: $this->orgProfileId,
        );
    }

    public function find(string $id): WebhookGetResponse
    {
        return $this->client->webhooks->retrieve(id: $id, xProfileID: $this->orgProfileId);
    }

    public function create(): WebhookBuilder
    {
        return new WebhookBuilder(client: $this->client, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function update(string $id): WebhookBuilder
    {
        return new WebhookBuilder(client: $this->client, id: $id, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    /**
     * Uses raw() instead of the SDK's typed delete(), which sends an empty-string body
     * with Content-Type: application/json and fails live ("value for 'body' is invalid").
     * A non-empty body works around it, the server ignores whatever key is inside; not a
     * real field, just filler to make the body non-empty. Confirmed on a real webhook,
     * created, deleted, and gone.
     */
    public function delete(string $id): void
    {
        $this->raw('delete', "v3/webhooks/{$id}", body: ['_' => true]);
    }

    public function enable(string $id, ?string $idempotencyKey = null, ?bool $sandbox = null): WebhookToggleStatusResponse
    {
        return $this->client->webhooks->toggleStatus(
            id: $id,
            isActive: true,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function disable(string $id, ?string $idempotencyKey = null, ?bool $sandbox = null): WebhookToggleStatusResponse
    {
        return $this->client->webhooks->toggleStatus(
            id: $id,
            isActive: false,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * rotate-secret doesn't check the webhook exists, so retrieve() runs first to fail on
     * an unknown id. Skipped when sandbox is on, since retrieve() has no sandbox mode and
     * would 404 an id that was never meant to persist.
     */
    public function rotateSecret(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): WebhookRotateSecretResponse
    {
        $sandbox = ($sandbox ?? $this->sandbox) ?: null;

        if ($sandbox === null) {
            $this->client->webhooks->retrieve(id: $id, xProfileID: $this->orgProfileId);
        }

        return $this->client->webhooks->rotateSecret(
            id: $id,
            sandbox: $sandbox,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function test(string $id, ?string $eventType = null, ?string $idempotencyKey = null, ?bool $sandbox = null): WebhookTestResponse
    {
        if ($eventType === null) {
            throw new InvalidArgumentException('An event type is required. Pass one via the $eventType parameter.');
        }

        return $this->client->webhooks->test(
            id: $id,
            eventType: $eventType,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function listEvents(string $id, int $page = 1, int $pageSize = 50, ?string $search = null): WebhookListEventsResponse
    {
        return $this->client->webhooks->listEvents(
            id: $id,
            page: $page,
            pageSize: $pageSize,
            search: $search,
            xProfileID: $this->orgProfileId,
        );
    }

    public function listEventTypes(): WebhookListEventTypesResponse
    {
        return $this->cached(
            'sent.webhook.event-types',
            fn () => $this->client->webhooks->listEventTypes(xProfileID: $this->orgProfileId),
        );
    }
}
