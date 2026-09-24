<?php

declare(strict_types=1);

namespace Sujip\SentDm\Channels;

use Illuminate\Notifications\Notification;
use LogicException;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;
use UnexpectedValueException;

class SentChannel
{
    public function __construct(private readonly Sent $sent) {}

    public function send(mixed $notifiable, Notification $notification): mixed
    {
        if (! is_callable([$notification, 'toSent'])) {
            throw new LogicException('Notifications using SentChannel must define a public toSent() method.');
        }

        $message = $notification->toSent($notifiable);

        if (! $message instanceof SentMessage) {
            throw new UnexpectedValueException('The notification toSent() method must return a SentMessage.');
        }

        $recipient = is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sent', $notification)
            : null;

        if (is_string($recipient) && $recipient !== '') {
            return $this->sent->send($message->to($recipient));
        }

        return null;
    }
}
