<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * @phpstan-require-extends Model
 */
trait HasSentContact
{
    public function routeNotificationForSent(Notification $_notification): string
    {
        return (string) ($this->getAttribute('phone') ?? '');
    }
}
