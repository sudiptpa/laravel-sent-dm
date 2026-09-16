<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function runDeduplicateMigration(): void
{
    $migration = require __DIR__.'/../../database/migrations/2026_09_16_000000_deduplicate_sent_logs_message_id.php';
    $migration->up();
}

function uniqueIndexMigration(): object
{
    return require __DIR__.'/../../database/migrations/2026_09_16_000001_add_unique_index_to_sent_logs_message_id.php';
}

beforeEach(function () {
    // RefreshDatabase already ran the unique index migration. Roll it back so
    // this file can insert the pre-upgrade duplicate rows it needs to test.
    uniqueIndexMigration()->down();
});

it('keeps the most recently updated row and drops the rest for a duplicated message_id', function () {
    DB::table('sent_logs')->insert([
        ['message_id' => 'msg-dup', 'status' => 'sent', 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
        ['message_id' => 'msg-dup', 'status' => 'delivered', 'created_at' => now()->subMinute(), 'updated_at' => now()],
        ['message_id' => 'msg-unique', 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()],
    ]);

    runDeduplicateMigration();

    $remaining = DB::table('sent_logs')->where('message_id', 'msg-dup')->get();

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->status)->toBe('delivered')
        ->and(DB::table('sent_logs')->where('message_id', 'msg-unique')->count())->toBe(1);
});

it('does nothing when no message_id is duplicated', function () {
    DB::table('sent_logs')->insert([
        ['message_id' => 'msg-a', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()],
        ['message_id' => 'msg-b', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['message_id' => null, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()],
    ]);

    runDeduplicateMigration();

    expect(DB::table('sent_logs')->count())->toBe(3);
});

it('leaves the table in a state the unique index migration can apply cleanly', function () {
    DB::table('sent_logs')->insert([
        ['message_id' => 'msg-dup', 'status' => 'sent', 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
        ['message_id' => 'msg-dup', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
    ]);

    runDeduplicateMigration();

    expect(fn () => uniqueIndexMigration()->up())->not->toThrow(Throwable::class);
});
