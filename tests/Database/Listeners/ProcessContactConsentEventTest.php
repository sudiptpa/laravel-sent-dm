<?php

declare(strict_types=1);

use Sujip\SentDm\Contracts\ResolvesSentTenant;
use Sujip\SentDm\Events\ContactOptedIn;
use Sujip\SentDm\Events\ContactOptedOut;
use Sujip\SentDm\Listeners\ProcessContactConsentEvent;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Webhooks\WebhookPayload;

/**
 * @param  array<string, mixed>  $data
 */
function contactPayload(string $event, array $data = []): WebhookPayload
{
    return WebhookPayload::fromArray([
        'field' => 'contact',
        'event' => $event,
        'payload' => array_merge(['from' => '+61412345678', 'to' => '+61498765432', 'contact_id' => 'c_1', 'source' => 'PROVIDER_SIGNAL'], $data),
    ]);
}

it('records an opt-out that arrived without any inbound message', function () {
    (new ProcessContactConsentEvent)->handle(new ContactOptedOut(contactPayload('contact.opt_out', ['opt_out' => true])));

    $record = SentOptOut::where('phone_number', '+61412345678')->first();

    expect(SentOptOut::isOptedOut('+61412345678'))->toBeTrue()
        ->and($record?->reason)->toBe('PROVIDER_SIGNAL');
});

it('records an opt-in and clears the opt-out', function () {
    SentOptOut::recordOptOut('+61412345678', 'STOP');

    (new ProcessContactConsentEvent)->handle(new ContactOptedIn(contactPayload('contact.opt_in', ['opt_out' => false])));

    expect(SentOptOut::isOptedOut('+61412345678'))->toBeFalse();
});

it('works when the payload leaves opt_out out', function () {
    (new ProcessContactConsentEvent)->handle(new ContactOptedOut(contactPayload('contact.opt_out')));

    expect(SentOptOut::isOptedOut('+61412345678'))->toBeTrue();
});

it('ignores a payload whose opt_out state contradicts the event', function () {
    (new ProcessContactConsentEvent)->handle(new ContactOptedOut(contactPayload('contact.opt_out', ['opt_out' => false])));

    expect(SentOptOut::isOptedOut('+61412345678'))->toBeFalse()
        ->and(SentOptOut::count())->toBe(0);
});

it('ignores an event with no contact number', function () {
    (new ProcessContactConsentEvent)->handle(new ContactOptedOut(contactPayload('contact.opt_out', ['from' => null])));

    expect(SentOptOut::count())->toBe(0);
});

it('falls back to a generic reason when the payload has no source', function () {
    (new ProcessContactConsentEvent)->handle(new ContactOptedOut(contactPayload('contact.opt_out', ['source' => null])));

    expect(SentOptOut::first()?->reason)->toBe('contact.opt_out');
});

it('uses the application resolver for the tenant', function () {
    $resolver = new class implements ResolvesSentTenant
    {
        public function forMessage(SentMessage $message, string $connection): string
        {
            return 'tenant-a';
        }

        public function forWebhook(WebhookPayload $payload): string
        {
            return 'tenant-b';
        }
    };
    app()->instance($resolver::class, $resolver);
    config(['sent.opt_out.tenant_resolver' => $resolver::class]);

    (new ProcessContactConsentEvent)->handle(new ContactOptedOut(contactPayload('contact.opt_out')));

    expect(SentOptOut::isOptedOut('+61412345678', 'tenant-b'))->toBeTrue()
        ->and(SentOptOut::isOptedOut('+61412345678', 'tenant-a'))->toBeFalse()
        ->and(SentOptOut::where('tenant', '')->exists())->toBeFalse();
});
