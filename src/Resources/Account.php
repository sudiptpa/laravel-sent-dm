<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Me\MeGetResponse;

class Account extends Resource
{
    public function get(): MeGetResponse
    {
        return $this->client->me->retrieve(xProfileID: $this->orgProfileId);
    }
}
