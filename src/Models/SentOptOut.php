<?php

declare(strict_types=1);

namespace Sujip\SentDm\Models;

use Illuminate\Database\Eloquent\Model;
use Sujip\SentDm\Support\OptOutScope;

class SentOptOut extends Model
{
    protected $table = 'sent_opt_outs';

    protected $fillable = [
        'phone_number',
        'scope',
        'opted_out',
        'reason',
        'last_opted_out_at',
        'last_opted_in_at',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'opted_out' => 'boolean',
            'last_opted_out_at' => 'datetime',
            'last_opted_in_at' => 'datetime',
        ];
    }

    public static function isOptedOut(string $phone, ?string $scope = null): bool
    {
        if ($phone === '') {
            return false;
        }

        $query = static::where('phone_number', $phone)->where('opted_out', true);

        if ($scope !== null) {
            $query->whereIn('scope', ['', OptOutScope::validate($scope)]);
        }

        return $query->exists();
    }

    public static function recordOptOut(string $phone, string $reason, ?string $scope = null): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone, 'scope' => $scope === null ? '' : OptOutScope::validate($scope)],
            ['opted_out' => true, 'reason' => $reason, 'last_opted_out_at' => now()],
        );
    }

    public static function recordOptIn(string $phone, ?string $scope = null): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone, 'scope' => $scope === null ? '' : OptOutScope::validate($scope)],
            ['opted_out' => false, 'reason' => null, 'last_opted_in_at' => now()],
        );
    }
}
