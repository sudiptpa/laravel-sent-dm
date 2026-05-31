<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Models\SentLog;

/**
 * @phpstan-require-extends Model
 */
trait HasSentMessages
{
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(SentLog::class, 'loggable');
    }

    public function lastSentMessage(): ?SentLog
    {
        return $this->sentMessages()->latest('id')->first();
    }

    public function sentMessagesWithStatus(SentLogStatus $status): MorphMany
    {
        return $this->sentMessages()->where('status', $status->value);
    }
}
