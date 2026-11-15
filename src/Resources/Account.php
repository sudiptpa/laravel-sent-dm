<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Me\APIResponseOfAccount;

class Account extends Resource
{
    public function get(): APIResponseOfAccount
    {
        return $this->client->me->retrieve(xProfileID: $this->orgProfileId);
    }
}
