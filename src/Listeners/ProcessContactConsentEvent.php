<?php

declare(strict_types=1);

namespace Sujip\SentDm\Listeners;

use Sujip\SentDm\Events\ContactOptedIn;
use Sujip\SentDm\Events\ContactOptedOut;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Support\SentTenant;

/**
 * Records the consent changes Sent reports in contact.opt_in and contact.opt_out webhooks.
 *
 * Some of these have no inbound message, for example a network handling STOP on the contact's
 * behalf, so the keyword listener alone would never hear about them. The contact's number is
 * `from` in these payloads.
 */
class ProcessContactConsentEvent
{
    public function handle(ContactOptedOut|ContactOptedIn $event): void
    {
        $payload = $event->payload;
        $contact = $payload->from();

        if ($contact === null || $contact === '') {
            return;
        }

        $optedOut = $event instanceof ContactOptedOut;

        // The payload states the resulting consent. If it disagrees with the event name, trust neither.
        if ($payload->optOut() !== null && $payload->optOut() !== $optedOut) {
            return;
        }

        $tenant = SentTenant::resolver()?->forWebhook($payload);

        if ($optedOut) {
            SentOptOut::recordOptOut($contact, $payload->source() ?? 'contact.opt_out', $tenant);

            return;
        }

        SentOptOut::recordOptIn($contact, $tenant);
    }
}
