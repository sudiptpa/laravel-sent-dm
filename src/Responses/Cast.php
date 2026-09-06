<?php

declare(strict_types=1);

namespace Sujip\SentDm\Responses;

/**
 * Narrows a decoded JSON value (always `mixed` to static analysis) to the type each
 * response class actually declares, without a reflection-based hydrator PHPStan can't
 * see through. A field of the wrong type becomes null rather than a thrown error, the
 * API returning a shape this package hasn't seen shouldn't crash the caller.
 */
final class Cast
{
    public static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /** @return array<array-key, mixed>|null */
    public static function arr(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /** @return list<array<array-key, mixed>> */
    public static function listOfArrays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** @return list<string> */
    public static function listOfStrings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
