<?php

declare(strict_types=1);

namespace Sujip\SentDm\Support;

use InvalidArgumentException;
use Sujip\SentDm\Contracts\ResolvesOptOutScope;

final class OptOutScope
{
    public static function resolver(): ?ResolvesOptOutScope
    {
        $class = config('sent.opt_out.scope_resolver');

        if ($class === null) {
            return null;
        }

        if (! is_string($class) || ! is_a($class, ResolvesOptOutScope::class, true)) {
            throw new InvalidArgumentException('The opt-out scope resolver must implement '.ResolvesOptOutScope::class.'.');
        }

        $resolver = app($class);

        if (! $resolver instanceof ResolvesOptOutScope) {
            throw new InvalidArgumentException('The opt-out scope resolver binding must implement '.ResolvesOptOutScope::class.'.');
        }

        return $resolver;
    }

    public static function validate(string $scope): string
    {
        if (trim($scope) === '' || mb_strlen($scope) > 191) {
            throw new InvalidArgumentException('An opt-out scope must contain between 1 and 191 characters.');
        }

        return $scope;
    }
}
