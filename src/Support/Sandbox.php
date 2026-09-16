<?php

declare(strict_types=1);

namespace Sujip\SentDm\Support;

final class Sandbox
{
    /**
     * An explicit sandbox value always wins over a resource or connection default,
     * including an explicit `false` used to force a real call. Resolves to null
     * (omit the field) rather than false, so a request body never carries a
     * pointless `sandbox: false` the caller didn't ask for.
     */
    public static function resolve(?bool $explicit, ?bool $default): ?bool
    {
        return ($explicit ?? $default) ?: null;
    }
}
