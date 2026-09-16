<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Sujip\SentDm\Models\SentOptOut;

/**
 * @phpstan-require-extends Model
 */
trait HasSentContact
{
    public function routeNotificationForSent(Notification $_notification): string
    {
        return $this->sentPhoneNumber();
    }

    public function optedOutFromSent(): bool
    {
        return SentOptOut::isOptedOut($this->sentPhoneNumber());
    }

    public function optOutFromSent(string $reason = 'manual'): void
    {
        SentOptOut::recordOptOut($this->sentPhoneNumber(), $reason);
    }

    public function optInToSent(): void
    {
        SentOptOut::recordOptIn($this->sentPhoneNumber());
    }

    protected function sentPhoneNumber(): string
    {
        return (string) ($this->getAttribute('phone') ?? '');
    }
}
