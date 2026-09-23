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

    public function optedOutFromSent(?string $scope = null): bool
    {
        return SentOptOut::isOptedOut($this->sentPhoneNumber(), $scope);
    }

    public function optOutFromSent(string $reason = 'manual', ?string $scope = null): void
    {
        SentOptOut::recordOptOut($this->sentPhoneNumber(), $reason, $scope);
    }

    public function optInToSent(?string $scope = null): void
    {
        SentOptOut::recordOptIn($this->sentPhoneNumber(), $scope);
    }

    protected function sentPhoneNumber(): string
    {
        return (string) ($this->getAttribute('phone') ?? '');
    }
}
