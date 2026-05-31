<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Profiles\APIResponseOfProfileDetail;
use SentDm\Profiles\ProfileListResponse;

class Profiles extends Resource
{
    public function get(): ProfileListResponse
    {
        return $this->cached(
            'sent.profiles.all',
            fn () => $this->client->profiles->list(),
        );
    }

    public function find(string $id): APIResponseOfProfileDetail
    {
        return $this->client->profiles->retrieve(profileID: $id);
    }

    public function delete(string $id): void
    {
        $this->client->profiles->delete(profileID: $id, body: []);
        $this->forget('sent.profiles.all');
    }
}
