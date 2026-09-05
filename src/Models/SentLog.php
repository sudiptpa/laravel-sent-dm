<?php

declare(strict_types=1);

namespace Sujip\SentDm\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
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

    // Query scopes: compose freely to build analytics queries ----------------

    /**
     * Filter by Sent.dm connection name (multi-tenant).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForConnection(Builder $query, string $connection): Builder
    {
        return $query->where('connection', $connection);
    }

    /**
     * Filter by delivery channel (sms, whatsapp, rcs).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Filter by template name.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTemplate(Builder $query, string $name): Builder
    {
        return $query->where('template_name', $name);
    }

    /**
     * Filter by delivery status.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForStatus(Builder $query, SentLogStatus|string $status): Builder
    {
        $value = $status instanceof SentLogStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Filter by recipient phone number (E.164).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForRecipient(Builder $query, string $recipient): Builder
    {
        return $query->where('recipient', $recipient);
    }

    /**
     * Filter by sent date range (inclusive, uses created_at).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereSentBetween(
        Builder $query,
        DateTimeInterface|string $from,
        DateTimeInterface|string $to,
    ): Builder {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Group by status with a COUNT. Attach to any filtered query to get a
     * status breakdown. Result rows have `status` and `total` properties.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGroupByStatus(Builder $query): Builder
    {
        return $query->selectRaw('status, COUNT(*) as total')->groupBy('status');
    }
}
