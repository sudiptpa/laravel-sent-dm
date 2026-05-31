<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Sujip\SentDm\Concerns\HasSentContact;

it('routes notification using phone attribute', function () {
    $model = new class extends Model
    {
        use HasSentContact;

        protected $attributes = ['phone' => '+61412345678'];
    };

    $notification = Mockery::mock(Notification::class);

    expect($model->routeNotificationForSent($notification))->toBe('+61412345678');
});

it('returns empty string when phone attribute not set', function () {
    $model = new class extends Model
    {
        use HasSentContact;
    };

    $notification = Mockery::mock(Notification::class);

    expect($model->routeNotificationForSent($notification))->toBe('');
});
