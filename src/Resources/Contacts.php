<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Contacts\APIResponseOfContact;
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

    public function find(string $id): APIResponseOfContact
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

    public function delete(string $id): void
    {
        $this->client->contacts->delete(id: $id, body: []);
        $this->forget("sent.contact.{$id}");
    }
}
