<?php

declare(strict_types=1);

namespace Sujip\SentDm\Support;

use InvalidArgumentException;
use Sujip\SentDm\Contracts\ResolvesTenantScope;

final class TenantScope
{
    public static function resolver(): ?ResolvesTenantScope
    {
        $class = config('sent.opt_out.tenant_scope_resolver');

        if ($class === null) {
            return null;
        }

        if (! is_string($class) || ! is_a($class, ResolvesTenantScope::class, true)) {
            throw new InvalidArgumentException('The tenant scope resolver must implement '.ResolvesTenantScope::class.'.');
        }

        $resolver = app($class);

        if (! $resolver instanceof ResolvesTenantScope) {
            throw new InvalidArgumentException('The tenant scope resolver binding must implement '.ResolvesTenantScope::class.'.');
        }

        return $resolver;
    }

    public static function validate(string $scope): string
    {
        if (trim($scope) === '' || mb_strlen($scope) > 191) {
            throw new InvalidArgumentException('A tenant scope must contain between 1 and 191 characters.');
        }

        return $scope;
    }
}
