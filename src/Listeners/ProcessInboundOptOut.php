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
            SentOptOut::recordOptOut($sender, $text);

            return;
        }

        if (in_array($text, $optInKeywords, strict: true)) {
            SentOptOut::recordOptIn($sender);
        }
    }
}
