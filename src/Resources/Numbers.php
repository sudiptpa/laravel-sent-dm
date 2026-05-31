<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Numbers\NumberLookupResponse;

class Numbers extends Resource
{
    public function lookup(string $phoneNumber): NumberLookupResponse
    {
        $key = 'sent.lookup.'.ltrim($phoneNumber, '+');

        return $this->cached($key, fn () => $this->client->numbers->lookup(phoneNumber: $phoneNumber));
    }
}
