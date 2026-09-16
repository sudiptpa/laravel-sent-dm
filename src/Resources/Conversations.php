<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Conversations\ConversationMessagesList\Message;
use SentDm\ConversationsPage;
use Sujip\SentDm\Concerns\Paginatable;

class Conversations extends Resource
{
    use Paginatable;

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
