<?php

declare(strict_types=1);

namespace Sujip\SentDm\Models;

use Illuminate\Database\Eloquent\Model;
use Sujip\SentDm\Support\TenantScope;

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

    public static function isOptedOut(string $phone, ?string $tenantScope = null): bool
    {
        if ($phone === '') {
            return false;
        }

        $query = static::where('phone_number', $phone)->where('opted_out', true);

        if ($tenantScope !== null) {
            $query->whereIn('scope', ['', TenantScope::validate($tenantScope)]);
        }

        return $query->exists();
    }

    public static function recordOptOut(string $phone, string $reason, ?string $tenantScope = null): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone, 'scope' => $tenantScope === null ? '' : TenantScope::validate($tenantScope)],
            ['opted_out' => true, 'reason' => $reason, 'last_opted_out_at' => now()],
        );
    }

    public static function recordOptIn(string $phone, ?string $tenantScope = null): void
    {
        if ($phone === '') {
            return;
        }

        static::updateOrCreate(
            ['phone_number' => $phone, 'scope' => $tenantScope === null ? '' : TenantScope::validate($tenantScope)],
            ['opted_out' => false, 'reason' => null, 'last_opted_in_at' => now()],
        );
    }
}
