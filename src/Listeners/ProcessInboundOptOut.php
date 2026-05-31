<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Models\SentOptOut;

class ProcessInboundOptOut
{
    public function handle(MessageReceived $event): void
    {
        $sender = $event->payload->sender();

        if ($sender === null) {
            return;
        }

        $text = strtoupper(trim($event->payload->text() ?? ''));

        /** @var list<string> $optOutKeywords */
        $optOutKeywords = config('sent.opt_out.keywords', ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT']);

        /** @var list<string> $optInKeywords */
        $optInKeywords = config('sent.opt_out.opt_in_keywords', ['START', 'YES', 'UNSTOP']);

        if (in_array($text, $optOutKeywords, strict: true)) {
            SentOptOut::updateOrCreate(
                ['phone_number' => $sender],
                ['opted_out' => true, 'reason' => $text, 'last_opted_out_at' => now()],
            );

            return;
        }

        if (in_array($text, $optInKeywords, strict: true)) {
            SentOptOut::updateOrCreate(
                ['phone_number' => $sender],
                ['opted_out' => false, 'reason' => null, 'last_opted_in_at' => now()],
            );
        }
    }
}
