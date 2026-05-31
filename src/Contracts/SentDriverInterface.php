<?php

declare(strict_types=1);

namespace Sujip\SentDm\Contracts;

use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\SentBulkDispatcher;

interface SentDriverInterface
{
    public function send(SentMessage $message): mixed;

    public function dispatch(SentMessage $message): void;

    /** @param array<int, string> $recipients */
    public function bulk(array $recipients): SentBulkDispatcher;
}
