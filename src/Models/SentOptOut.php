<?php

declare(strict_types=1);

namespace Sujip\SentDm\Models;

use Illuminate\Database\Eloquent\Model;
use Sujip\SentDm\Support\SentTenant;

class SentOptOut extends Model
{
    protected $table = 'sent_opt_outs';

    protected $fillable = [
        'phone_number',
        'tenant',
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

    public static function isOptedOut(string $phone, ?string $tenant = null): bool
    {
        if ($phone === '') {
            return false;
        }

        $query = static::where('phone_number', $phone)->where('opted_out', true);

        if ($tenant !== null) {
            $query->whereIn('tenant', ['', SentTenant::validate($tenant)]);
        }

        return $query->exists();
    }

    public static function recordOptOut(string $phone, string $reason, ?string $tenant = null): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone, 'tenant' => $tenant === null ? '' : SentTenant::validate($tenant)],
            ['opted_out' => true, 'reason' => $reason, 'last_opted_out_at' => now()],
        );
    }

    public static function recordOptIn(string $phone, ?string $tenant = null): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone, 'tenant' => $tenant === null ? '' : SentTenant::validate($tenant)],
            ['opted_out' => false, 'reason' => null, 'last_opted_in_at' => now()],
        );
    }
}
