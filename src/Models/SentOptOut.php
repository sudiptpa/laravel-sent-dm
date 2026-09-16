<?php

declare(strict_types=1);

namespace Sujip\SentDm\Models;

use Illuminate\Database\Eloquent\Model;

class SentOptOut extends Model
{
    protected $table = 'sent_opt_outs';

    protected $fillable = [
        'phone_number',
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

    public static function isOptedOut(string $phone): bool
    {
        if ($phone === '') {
            return false;
        }

        return static::where('phone_number', $phone)->where('opted_out', true)->exists();
    }

    public static function recordOptOut(string $phone, string $reason): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone],
            ['opted_out' => true, 'reason' => $reason, 'last_opted_out_at' => now()],
        );
    }

    public static function recordOptIn(string $phone): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone],
            ['opted_out' => false, 'reason' => null, 'last_opted_in_at' => now()],
        );
    }
}
