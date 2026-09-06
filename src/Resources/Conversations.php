<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Conversations\ConversationListMessagesResponse;
use SentDm\Conversations\ConversationListResponse;

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

    public function get(): ConversationListResponse
    {
        return $this->client->conversations->list(
            page: $this->page,
            pageSize: $this->pageSize,
            xProfileID: $this->orgProfileId,
        );
    }

    public function messages(string $id): ConversationListMessagesResponse
    {
        return $this->client->conversations->listMessages(
            id: $id,
            page: $this->page,
            pageSize: $this->pageSize,
            xProfileID: $this->orgProfileId,
        );
    }
}
