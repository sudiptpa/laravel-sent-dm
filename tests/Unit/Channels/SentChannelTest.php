<?php

declare(strict_types=1);

use Illuminate\Notifications\Notification;
use Sujip\SentDm\Channels\SentChannel;
use Sujip\SentDm\Contracts\ProvidesSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;

function makeNotifiable(string $phone): object
{
    return new class($phone)
    {
        public function __construct(private string $phone) {}

        public function routeNotificationFor(string $_driver, mixed $_notification): string
        {
            return $this->phone;
        }
    };
}

function makeNotification(SentMessage $message): Notification&ProvidesSentMessage
{
    return new class($message) extends Notification implements ProvidesSentMessage
    {
        public function __construct(private SentMessage $message) {}

        public function toSent(mixed $_notifiable): SentMessage
        {
            return $this->message;
        }
    };
}

it('sends message via the Sent manager', function () {
    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')->once()->andReturn(null);

    $channel = new SentChannel($sent);
    $channel->send(
        makeNotifiable('+61412345678'),
        makeNotification(SentMessage::create()->message('Hello'))
    );
});

it('sets recipient from notifiable route', function () {
    $sent = Mockery::mock(Sent::class);
    $sent->shouldReceive('send')
        ->once()
        ->withArgs(fn (SentMessage $m) => $m->getRecipient() === '+61412345678')
        ->andReturn(null);

    $channel = new SentChannel($sent);
    $channel->send(
        makeNotifiable('+61412345678'),
        makeNotification(SentMessage::create()->template('otp'))
    );
});

it('skips send when notifiable has no recipient', function () {
    $sent = Mockery::mock(Sent::class);
    $sent->shouldNotReceive('send');

    $notifiable = new class
    {
        public function routeNotificationFor(string $_driver, mixed $_notification): string
        {
            return '';
        }
    };

    $channel = new SentChannel($sent);
    $channel->send($notifiable, makeNotification(SentMessage::create()->message('Hello')));
});

it('returns null when toSent returns non-SentMessage', function () {
    $sent = Mockery::mock(Sent::class);
    $sent->shouldNotReceive('send');

    $notifiable = makeNotifiable('+61412345678');

    $notification = new class extends Notification
    {
        public function toSent(mixed $_notifiable): string
        {
            return 'not a SentMessage';
        }
    };

    $result = (new SentChannel($sent))->send($notifiable, $notification);

    expect($result)->toBeNull();
});

it('returns null when notifiable is not an object', function () {
    $sent = Mockery::mock(Sent::class);
    $sent->shouldNotReceive('send');

    $channel = new SentChannel($sent);
    $result = $channel->send('not-an-object', makeNotification(SentMessage::create()));

    expect($result)->toBeNull();
});
