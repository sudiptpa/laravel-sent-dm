<?php

declare(strict_types=1);

namespace Sujip\SentDm\Channels;

use Illuminate\Notifications\Notification;
use Sujip\SentDm\Contracts\ProvidesSentMessage;
use Sujip\SentDm\Sent;

class SentChannel
{
    public function __construct(private readonly Sent $sent) {}

    public function send(mixed $notifiable, Notification $notification): mixed
    {
        if (! $notification instanceof ProvidesSentMessage) {
            return null;
        }

        $message = $notification->toSent($notifiable);

        $recipient = is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sent', $notification)
            : null;

        if (is_string($recipient) && $recipient !== '') {
            return $this->sent->send($message->to($recipient));
        }

        return null;
    }
}
