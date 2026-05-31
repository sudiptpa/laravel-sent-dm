<?php

declare(strict_types=1);

namespace Sujip\SentDm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Sujip\SentDm\Enums\SentLogStatus;

class SentLog extends Model
{
    protected $table = 'sent_logs';

    protected $fillable = [
        'connection',
        'recipient',
        'channel',
        'template_name',
        'message_id',
        'idempotency_key',
        'status',
        'loggable_type',
        'loggable_id',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => SentLogStatus::class,
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
