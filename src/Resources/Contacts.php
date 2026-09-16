<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Contacts\APIResponseOfContact;
use SentDm\Contacts\APIResponseOfContactMessageSummary;
use SentDm\Contacts\ContactResponse;
use SentDm\ContactsPage;
use Sujip\SentDm\Builders\ContactBuilder;
use Sujip\SentDm\Concerns\Paginatable;
use Sujip\SentDm\Support\Sandbox;

class Contacts extends Resource
{
    use Paginatable;

    private ?string $search = null;

    private ?string $channel = null;

    private ?string $phone = null;

    /**
     * Matches the contact's national-format phone number exactly, including
     * punctuation (e.g. `555-0142`). A digit-only substring without the hyphen
     * (`5550142`) will not match, verified against the live API, not documented
     * anywhere upstream. Contacts have no name field; there's nothing else to search.
     */
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

    public function phone(string $phone): static
    {
        $clone = clone $this;
        $clone->phone = $phone;

        return $clone;
    }

    /** @return ContactsPage<ContactResponse> */
    public function get(): ContactsPage
    {
        return $this->client->contacts->list(
            page: $this->page,
            pageSize: $this->pageSize,
            search: $this->search,
            channel: $this->channel,
            phone: $this->phone,
            xProfileID: $this->orgProfileId,
        );
    }

    public function find(string $id): APIResponseOfContact
    {
        return $this->cached(
            "sent.contact.{$id}",
            fn () => $this->client->contacts->retrieve(id: $id, xProfileID: $this->orgProfileId),
        );
    }

    public function create(): ContactBuilder
    {
        return new ContactBuilder(client: $this->client, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function update(string $id): ContactBuilder
    {
        return new ContactBuilder(
            client: $this->client,
            id: $id,
            profileId: $this->orgProfileId,
            sandboxDefault: $this->sandbox,
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
    public function delete(string $id, ?bool $sandbox = null): void
    {
        $this->client->contacts->delete(
            id: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
        $this->forget("sent.contact.{$id}");
        $this->forget("sent.contact.{$id}.message-summary");
    }

    public function messageSummary(string $id): APIResponseOfContactMessageSummary
    {
        return $this->cached(
            "sent.contact.{$id}.message-summary",
            fn () => $this->client->contacts->retrieveMessageSummary(contactID: $id, xProfileID: $this->orgProfileId),
        );
    }
}
