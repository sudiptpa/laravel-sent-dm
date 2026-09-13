<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Conversations\ConversationMessagesList\Message;
use SentDm\ConversationsPage;

class Conversations extends Resource
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

    /** @return ConversationsPage<Message> */
    public function get(): ConversationsPage
    {
        return $this->client->conversations->list(
            page: $this->page,
            pageSize: $this->pageSize,
            xProfileID: $this->orgProfileId,
        );
    }

    /** @return ConversationsPage<Message> */
    public function messages(string $id): ConversationsPage
    {
        return $this->client->conversations->listMessages(
            id: $id,
            page: $this->page,
            pageSize: $this->pageSize,
            xProfileID: $this->orgProfileId,
        );
    }
}
