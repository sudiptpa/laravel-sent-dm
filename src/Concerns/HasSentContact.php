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

    public function optedOutFromSent(?string $tenantScope = null): bool
    {
        return SentOptOut::isOptedOut($this->sentPhoneNumber(), $tenantScope);
    }

    public function optOutFromSent(string $reason = 'manual', ?string $tenantScope = null): void
    {
        SentOptOut::recordOptOut($this->sentPhoneNumber(), $reason, $tenantScope);
    }

    public function optInToSent(?string $tenantScope = null): void
    {
        SentOptOut::recordOptIn($this->sentPhoneNumber(), $tenantScope);
    }

    protected function sentPhoneNumber(): string
    {
        return (string) ($this->getAttribute('phone') ?? '');
    }
}
