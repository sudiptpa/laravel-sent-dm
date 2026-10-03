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

    public function optedOutFromSent(?string $tenant = null): bool
    {
        return SentOptOut::isOptedOut($this->sentPhoneNumber(), $tenant);
    }

    public function optOutFromSent(string $reason = 'manual', ?string $tenant = null): void
    {
        SentOptOut::recordOptOut($this->sentPhoneNumber(), $reason, $tenant);
    }

    public function optInToSent(?string $tenant = null): void
    {
        SentOptOut::recordOptIn($this->sentPhoneNumber(), $tenant);
    }

    protected function sentPhoneNumber(): string
    {
        return (string) ($this->getAttribute('phone') ?? '');
    }
}
