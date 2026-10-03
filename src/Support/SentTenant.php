<?php

declare(strict_types=1);

namespace Sujip\SentDm\Support;

use InvalidArgumentException;
use Sujip\SentDm\Contracts\ResolvesSentTenant;

final class SentTenant
{
    public static function resolver(): ?ResolvesSentTenant
    {
        $class = config('sent.opt_out.tenant_resolver');

        if ($class === null) {
            return null;
        }

        if (! is_string($class) || ! is_a($class, ResolvesSentTenant::class, true)) {
            throw new InvalidArgumentException('The tenant resolver must implement '.ResolvesSentTenant::class.'.');
        }

        $resolver = app($class);

        if (! $resolver instanceof ResolvesSentTenant) {
            throw new InvalidArgumentException('The tenant resolver binding must implement '.ResolvesSentTenant::class.'.');
        }

        return $resolver;
    }

    public static function validate(string $tenant): string
    {
        if (trim($tenant) === '' || mb_strlen($tenant) > 191) {
            throw new InvalidArgumentException('A tenant must contain between 1 and 191 characters.');
        }

        return $tenant;
    }
}
