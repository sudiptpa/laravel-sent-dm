<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Sujip\SentDm\Concerns\HasSentContact;
use Sujip\SentDm\Models\SentOptOut;

class PhoneUser extends Model
{
    use HasSentContact;

    protected $table = 'phone_users';

    public $timestamps = false;

    protected $fillable = ['phone'];
}

beforeEach(function () {
    Schema::create('phone_users', function ($table) {
        $table->id();
        $table->string('phone')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('phone_users');
});

it('routeNotificationForSent returns the phone attribute', function () {
    $user = PhoneUser::create(['phone' => '+61412345678']);

    expect($user->routeNotificationForSent(Mockery::mock(Notification::class)))->toBe('+61412345678');
});

it('optedOutFromSent returns false when no opt-out record exists', function () {
    $user = PhoneUser::create(['phone' => '+61412345678']);

    expect($user->optedOutFromSent())->toBeFalse();
});

it('optedOutFromSent returns true when record has opted_out=true', function () {
    $user = PhoneUser::create(['phone' => '+61412345678']);
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    expect($user->optedOutFromSent())->toBeTrue();
});

it('optedOutFromSent returns false when record has opted_out=false', function () {
    $user = PhoneUser::create(['phone' => '+61412345678']);
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => false]);

    expect($user->optedOutFromSent())->toBeFalse();
});

it('optedOutFromSent returns false when phone is empty', function () {
    $user = PhoneUser::create(['phone' => null]);

    expect($user->optedOutFromSent())->toBeFalse();
});

it('optOutFromSent creates an opt-out record', function () {
    $user = PhoneUser::create(['phone' => '+61412345678']);
    $user->optOutFromSent('manual');

    $record = SentOptOut::where('phone_number', '+61412345678')->first();
    expect($record?->opted_out)->toBeTrue()
        ->and($record?->reason)->toBe('manual');
});

it('optOutFromSent does nothing when phone is empty', function () {
    $user = PhoneUser::create(['phone' => null]);
    $user->optOutFromSent();

    expect(SentOptOut::count())->toBe(0);
});

it('optInToSent creates an opt-in record', function () {
    $user = PhoneUser::create(['phone' => '+61412345678']);
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);
    $user->optInToSent();

    expect(SentOptOut::where('phone_number', '+61412345678')->first()?->opted_out)->toBeFalse();
});

it('optInToSent does nothing when phone is empty', function () {
    $user = PhoneUser::create(['phone' => null]);
    $user->optInToSent();

    expect(SentOptOut::count())->toBe(0);
});
