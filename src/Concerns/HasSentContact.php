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
        $phone = $this->sentPhoneNumber();

        if ($phone === '') {
            return false;
        }

        return SentOptOut::where('phone_number', $phone)
            ->where('opted_out', true)
            ->exists();
    }

    public function optOutFromSent(string $reason = 'manual'): void
    {
        $this->updateOptOutRecord(['opted_out' => true, 'reason' => $reason, 'last_opted_out_at' => now()]);
    }

    public function optInToSent(): void
    {
        $this->updateOptOutRecord(['opted_out' => false, 'last_opted_in_at' => now()]);
    }

    protected function sentPhoneNumber(): string
    {
        return (string) ($this->getAttribute('phone') ?? '');
    }

    /** @param array<string, mixed> $attributes */
    private function updateOptOutRecord(array $attributes): void
    {
        $phone = $this->sentPhoneNumber();

        if ($phone === '') {
            return;
        }

        SentOptOut::updateOrCreate(['phone_number' => $phone], $attributes);
    }
}
