<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('shows stats table grouped by status', function () {
    DB::table('sent_logs')->insert([
        ['message_id' => 'msg-1', 'status' => 'queued', 'recipient' => '+61412345678', 'connection' => 'default', 'created_at' => now(), 'updated_at' => now()],
        ['message_id' => 'msg-2', 'status' => 'delivered', 'recipient' => '+61412345678', 'connection' => 'default', 'created_at' => now(), 'updated_at' => now()],
        ['message_id' => 'msg-3', 'status' => 'delivered', 'recipient' => '+61412345678', 'connection' => 'default', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('sent:stats')
        ->expectsOutputToContain('Queued')
        ->expectsOutputToContain('Delivered')
        ->assertExitCode(0);
});

it('shows info message when no logs exist', function () {
    $this->artisan('sent:stats')
        ->expectsOutputToContain('No messages logged yet')
        ->assertExitCode(0);
});

it('shows failure when table does not exist', function () {
    $this->artisan('sent:stats', ['--table' => 'nonexistent_table'])
        ->expectsOutputToContain('Could not query table')
        ->assertExitCode(1);
});

it('shows stats for custom table option', function () {
    DB::table('sent_logs')->insert([
        ['message_id' => 'msg-1', 'status' => 'sent', 'recipient' => '+61412345678', 'connection' => 'default', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('sent:stats', ['--table' => 'sent_logs'])
        ->expectsOutputToContain('Sent')
        ->assertExitCode(0);
});

it('shows unknown statuses not in the enum', function () {
    DB::table('sent_logs')->insert([
        ['message_id' => 'msg-1', 'status' => 'routed', 'recipient' => '+61412345678', 'connection' => 'default', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('sent:stats')
        ->expectsOutputToContain('Routed')
        ->assertExitCode(0);
});
