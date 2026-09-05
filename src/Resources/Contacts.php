<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Contacts\ContactGetMessageSummaryResponse;
use SentDm\Contacts\ContactGetResponse;
use SentDm\Contacts\ContactListResponse;
use Sujip\SentDm\Builders\ContactBuilder;

class Contacts extends Resource
{
    private int $page = 1;

    private int $pageSize = 50;

    private ?string $search = null;

    private ?string $channel = null;

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

    public function channel(string $channel): static
    {
        $clone = clone $this;
        $clone->channel = $channel;

        return $clone;
    }

    public function get(): ContactListResponse
    {
        return $this->client->contacts->list(
            page: $this->page,
            pageSize: $this->pageSize,
            search: $this->search,
            channel: $this->channel,
        );
    }

    public function find(string $id): ContactGetResponse
    {
        return $this->cached(
            "sent.contact.{$id}",
            fn () => $this->client->contacts->retrieve(id: $id),
        );
    }

    public function create(): ContactBuilder
    {
        return new ContactBuilder(client: $this->client);
    }

    public function update(string $id): ContactBuilder
    {
        return new ContactBuilder(
            client: $this->client,
            id: $id,
            onSaved: fn () => $this->forget("sent.contact.{$id}"),
        );
    }

    /**
     * @deprecated Sent.dm's August 2026 platform changelog deprecates `DELETE
     * /v3/contacts/{id}` in favor of opting the contact out, which stops every send but
     * keeps the record of who they were and that they asked: `contacts()->update($id)
     * ->optOut(true)->save()`. The endpoint still works and this method is unchanged, but
     * prefer the opt-out path for new code.
     */
    public function delete(string $id): void
    {
        $this->client->contacts->delete(id: $id);
        $this->forget("sent.contact.{$id}");
        $this->forget("sent.contact.{$id}.message-summary");
    }

    public function messageSummary(string $id): ContactGetMessageSummaryResponse
    {
        return $this->cached(
            "sent.contact.{$id}.message-summary",
            fn () => $this->client->contacts->retrieveMessageSummary(contactID: $id),
        );
    }
}
