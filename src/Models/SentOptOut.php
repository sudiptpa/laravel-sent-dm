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
}
