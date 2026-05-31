<?php

declare(strict_types=1);

namespace Sujip\SentDm\Contracts;

use Sujip\SentDm\Messages\SentMessage;

interface ProvidesSentMessage
{
    public function toSent(mixed $notifiable): SentMessage;
}
