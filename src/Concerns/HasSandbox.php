<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

trait HasSandbox
{
    private ?bool $sandbox = null;

    /**
     * Explicit here always wins over the global `SENT_SANDBOX` config. Call
     * `sandbox(false)` to force a real save even when `SENT_SANDBOX=true` is set.
     */
    public function sandbox(bool $sandbox = true): static
    {
        $clone = clone $this;
        $clone->sandbox = $sandbox;

        return $clone;
    }
}
