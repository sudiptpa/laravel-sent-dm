<?php

declare(strict_types=1);

namespace Sujip\SentDm\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Sujip\SentDm\SentManager;

/**
 * Validates that a value is a reachable E.164 phone number via the Sent.dm
 * number-lookup API. Fails open when the API is unavailable so a network
 * blip doesn't block form submissions.
 *
 * Usage:
 *   'phone' => [new SentMobileNumber()]
 *   'phone' => [Rule::sentMobileNumber()]
 *   'phone' => [Rule::sentMobileNumber(requireMobile: true)]
 */
class SentMobileNumber implements ValidationRule
{
    public function __construct(
        private readonly ?string $connection = null,
        private readonly bool $requireMobile = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = is_string($value) ? $value : '';

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $number)) {
            $fail("The {$attribute} must be a valid E.164 phone number (e.g. +61412345678).");

            return;
        }

        try {
            /** @var SentManager $manager */
            $manager = app(SentManager::class);
            $response = $manager->connection($this->connection)->lookup($number);
            $data = $response->data;

            if ($data === null || ! ($data->isValid ?? false)) {
                $fail("The {$attribute} is not a valid or reachable phone number.");

                return;
            }

            if ($this->requireMobile && $data->lineType !== 'mobile') {
                $fail("The {$attribute} must be a mobile number.");
            }
        } catch (\Throwable) {
            // Fail open. Network errors and API unavailability must not block valid form submissions.
        }
    }
}
