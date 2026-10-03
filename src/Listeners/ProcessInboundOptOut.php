<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use Sujip\SentDm\Events\MessageReceived;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Support\OptOutScope;

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
            $scope = OptOutScope::resolver()?->forWebhook($event->payload);
            SentOptOut::recordOptOut($sender, $text, $scope);

            return;
        }

        if (in_array($text, $optInKeywords, strict: true)) {
            $scope = OptOutScope::resolver()?->forWebhook($event->payload);
            SentOptOut::recordOptIn($sender, $scope);
        }
    }
}
