<?php

declare(strict_types=1);

namespace Sujip\SentDm\Exceptions;

use RuntimeException;

final class ContactOptedOutException extends RuntimeException
{
    public function __construct(public readonly string $phoneNumber)
    {
        parent::__construct("The contact [{$phoneNumber}] has opted out and cannot receive messages.");
    }
}
