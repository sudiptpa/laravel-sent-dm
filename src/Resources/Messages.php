<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Messages\MessageGetActivitiesResponse;
use SentDm\Messages\MessageGetStatusResponse;

class Messages extends Resource
{
    public function retrieve(string $id): MessageGetStatusResponse
    {
        return $this->client->messages->retrieveStatus(id: $id);
    }

    public function activities(string $id): MessageGetActivitiesResponse
    {
        return $this->client->messages->retrieveActivities(id: $id);
    }
}
