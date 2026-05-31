<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Sujip\SentDm\Concerns\HasSentMessages;
use Sujip\SentDm\Enums\SentLogStatus;
use Sujip\SentDm\Models\SentLog;

// Minimal in-memory model for the trait
class TestableUser extends Model
{
    use HasSentMessages;

    protected $table = 'testable_users';

    public $timestamps = false;

    protected $fillable = ['id'];

    public function getMorphClass(): string
    {
        return 'App\\Models\\User';
    }
}

beforeEach(function () {
    Schema::create('testable_users', function ($table) {
        $table->id();
    });

    TestableUser::create(['id' => 1]);
    TestableUser::create(['id' => 2]);
});

afterEach(function () {
    Schema::dropIfExists('testable_users');
});

it('sentMessages() returns a MorphMany relationship', function () {
    $user = TestableUser::find(1);

    SentLog::create([
        'recipient' => '+61412345678',
        'status' => SentLogStatus::Delivered,
        'loggable_type' => 'App\\Models\\User',
        'loggable_id' => '1',
    ]);

    expect($user->sentMessages)->toHaveCount(1);
});

it('sentMessages() scopes to the correct model', function () {
    $user1 = TestableUser::find(1);
    $user2 = TestableUser::find(2);

    SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Delivered, 'loggable_type' => 'App\\Models\\User', 'loggable_id' => '1']);
    SentLog::create(['recipient' => '+61498765432', 'status' => SentLogStatus::Delivered, 'loggable_type' => 'App\\Models\\User', 'loggable_id' => '2']);

    expect($user1->sentMessages)->toHaveCount(1)
        ->and($user2->sentMessages)->toHaveCount(1);
});

it('lastSentMessage() returns the most recent log', function () {
    $user = TestableUser::find(1);

    SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Queued, 'loggable_type' => 'App\\Models\\User', 'loggable_id' => '1', 'template_name' => 'first']);
    SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Delivered, 'loggable_type' => 'App\\Models\\User', 'loggable_id' => '1', 'template_name' => 'second']);

    expect($user->lastSentMessage()?->template_name)->toBe('second');
});

it('lastSentMessage() returns null when no messages exist', function () {
    expect(TestableUser::find(1)->lastSentMessage())->toBeNull();
});

it('sentMessagesWithStatus() filters by status', function () {
    $user = TestableUser::find(1);

    SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Delivered, 'loggable_type' => 'App\\Models\\User', 'loggable_id' => '1']);
    SentLog::create(['recipient' => '+61412345678', 'status' => SentLogStatus::Failed, 'loggable_type' => 'App\\Models\\User', 'loggable_id' => '1']);

    expect($user->sentMessagesWithStatus(SentLogStatus::Delivered)->get())->toHaveCount(1)
        ->and($user->sentMessagesWithStatus(SentLogStatus::Failed)->get())->toHaveCount(1)
        ->and($user->sentMessagesWithStatus(SentLogStatus::Read)->get())->toHaveCount(0);
});
