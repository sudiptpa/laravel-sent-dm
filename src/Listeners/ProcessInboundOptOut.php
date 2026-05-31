<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Models\SentOptOut;

class ProcessInboundOptOut
{
    private const OPT_OUT_KEYWORDS = ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];

    private const OPT_IN_KEYWORDS = ['START', 'YES', 'UNSTOP'];

    public function handle(MessageReceived $event): void
    {
        $sender = $event->payload->sender();

        if ($sender === null) {
            return;
        }

        $text = strtoupper(trim($event->payload->text() ?? ''));

        if (in_array($text, self::OPT_OUT_KEYWORDS, strict: true)) {
            SentOptOut::updateOrCreate(
                ['phone_number' => $sender],
                ['opted_out' => true, 'reason' => $text, 'last_opted_out_at' => now()],
            );

            return;
        }

        if (in_array($text, self::OPT_IN_KEYWORDS, strict: true)) {
            SentOptOut::updateOrCreate(
                ['phone_number' => $sender],
                ['opted_out' => false, 'last_opted_in_at' => now()],
            );
        }
    }
}
