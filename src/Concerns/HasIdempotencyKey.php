<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

trait HasIdempotencyKey
{
    private ?string $idempotencyKey = null;

    /**
     * 1-255 alphanumeric characters, hyphens, or underscores. A retry within 24 hours
     * with the same key returns the original response instead of creating a duplicate.
     */
    public function idempotencyKey(string $key): static
    {
        $clone = clone $this;
        $clone->idempotencyKey = $key;

        return $clone;
    }
}
