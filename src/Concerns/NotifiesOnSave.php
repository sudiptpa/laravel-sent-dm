<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

use Closure;

trait NotifiesOnSave
{
    private ?Closure $onSaved = null;

    private function notifySaved(): void
    {
        if ($this->onSaved !== null) {
            ($this->onSaved)();
        }
    }
}
