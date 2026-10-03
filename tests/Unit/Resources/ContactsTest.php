<?php

declare(strict_types=1);

use Sujip\SentDm\Builders\ContactBuilder;

it('contacts()->get() lists contacts', function () {
    $result = sentApi([
        'contacts' => [[
            'id' => 'c-1',
            'customer_id' => 'cust-1',
            'phone_number' => '+1234567890',
            'format_e164' => '+1234567890',
            'format_international' => '+1 234-567-890',
            'format_national' => '(234) 567-890',
            'format_rfc' => 'tel:+1-234-567-890',
            'country_code' => '1',
            'region_code' => 'US',
            'available_channels' => 'sms,whatsapp',
            'default_channel' => 'sms',
            'opt_out' => false,
            'is_inherited' => false,
            'created_at' => '2026-09-11T14:57:37+00:00',
            'updated_at' => null,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 150,
            'total_pages' => 8, 'has_more' => true, 'cursors' => null,
        ],
    ])->contacts()->get();

    $contact = $result->data->contacts[0];
    expect($contact->id)->toBe('c-1')
        ->and($contact->customerID)->toBe('cust-1')
        ->and($contact->phoneNumber)->toBe('+1234567890')
        ->and($contact->formatE164)->toBe('+1234567890')
        ->and($contact->formatInternational)->toBe('+1 234-567-890')
        ->and($contact->formatNational)->toBe('(234) 567-890')
        ->and($contact->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($contact->countryCode)->toBe('1')
        ->and($contact->regionCode)->toBe('US')
        ->and($contact->availableChannels)->toBe('sms,whatsapp')
        ->and($contact->defaultChannel)->toBe('sms')
        ->and($contact->optOut)->toBeFalse()
        ->and($contact->isInherited)->toBeFalse()
        ->and($contact->updatedAt)->toBeNull();
});

it('contacts()->search()->channel()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->contacts();
    $chained = $base->search('John')->channel('whatsapp')->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('contacts()->search()->get() passes search param', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])
        ->contacts()
        ->search('John')
        ->get();
    expect($result)->not->toBeNull();
});

it('contacts()->phone()->get() passes the phone filter param', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])
        ->contacts()
        ->phone('+12125550199')
        ->get();
    expect($result)->not->toBeNull();
});

it('contacts()->find() retrieves a contact', function () {
    $result = sentApi([
        'id' => 'c-1',
        'customer_id' => 'cust-1',
        'phone_number' => '+1234567890',
        'format_e164' => '+1234567890',
        'format_international' => '+1 234-567-890',
        'format_national' => '(234) 567-890',
        'format_rfc' => 'tel:+1-234-567-890',
        'country_code' => '1',
        'region_code' => 'US',
        'available_channels' => 'sms',
        'default_channel' => 'sms',
        'opt_out' => false,
        'is_inherited' => false,
        'created_at' => '2026-09-11T14:57:37+00:00',
        'updated_at' => '2026-09-12T00:00:00+00:00',
    ])->contacts()->find('c-1');

    expect($result->data->id)->toBe('c-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->phoneNumber)->toBe('+1234567890')
        ->and($result->data->formatE164)->toBe('+1234567890')
        ->and($result->data->formatInternational)->toBe('+1 234-567-890')
        ->and($result->data->formatNational)->toBe('(234) 567-890')
        ->and($result->data->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($result->data->countryCode)->toBe('1')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->availableChannels)->toBe('sms')
        ->and($result->data->defaultChannel)->toBe('sms')
        ->and($result->data->optOut)->toBeFalse()
        ->and($result->data->isInherited)->toBeFalse()
        ->and($result->data->updatedAt)->not->toBeNull();
});

it('contacts()->create() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->create())->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->create()->phone()->save() creates a contact', function () {
    $result = sentApi([
        'id' => 'c-1',
        'customer_id' => 'cust-1',
        'phone_number' => '+1234567890',
        'format_e164' => '+1234567890',
        'format_international' => '+1 234-567-890',
        'format_national' => '(234) 567-890',
        'format_rfc' => 'tel:+1-234-567-890',
        'country_code' => '1',
        'region_code' => 'US',
        'available_channels' => 'sms',
        'default_channel' => 'sms',
        'opt_out' => false,
        'is_inherited' => false,
        'created_at' => '2026-09-11T14:57:37+00:00',
        'updated_at' => null,
    ])
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->save();

    expect($result->data->id)->toBe('c-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->phoneNumber)->toBe('+1234567890')
        ->and($result->data->formatE164)->toBe('+1234567890')
        ->and($result->data->formatInternational)->toBe('+1 234-567-890')
        ->and($result->data->formatNational)->toBe('(234) 567-890')
        ->and($result->data->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($result->data->countryCode)->toBe('1')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->availableChannels)->toBe('sms')
        ->and($result->data->defaultChannel)->toBe('sms')
        ->and($result->data->optOut)->toBeFalse()
        ->and($result->data->isInherited)->toBeFalse()
        ->and($result->data->updatedAt)->toBeNull();
});

it('contacts()->create()->save() throws without a phone number', function () {
    sentApi()->contacts()->create()->save();
})->throws(InvalidArgumentException::class, 'phone number is required');

it('contacts()->create()->defaultChannel()->save() throws, defaultChannel is update-only', function () {
    sentApi()
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->defaultChannel('sms')
        ->save();
})->throws(InvalidArgumentException::class, 'defaultChannel');

it('contacts()->create()->optOut()->save() throws, optOut is update-only', function () {
    sentApi()
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->optOut(true)
        ->save();
})->throws(InvalidArgumentException::class, 'optOut');

it('contacts()->update() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->update('c-1'))->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->update()->optOut()->save() updates a contact', function () {
    $result = sentApi([
        'id' => 'c-1',
        'customer_id' => 'cust-1',
        'phone_number' => '+1234567890',
        'format_e164' => '+1234567890',
        'format_international' => '+1 234-567-890',
        'format_national' => '(234) 567-890',
        'format_rfc' => 'tel:+1-234-567-890',
        'country_code' => '1',
        'region_code' => 'US',
        'available_channels' => 'sms,whatsapp',
        'default_channel' => 'whatsapp',
        'opt_out' => true,
        'is_inherited' => false,
        'created_at' => '2026-08-12T14:57:37+00:00',
        'updated_at' => '2026-09-11T14:57:37+00:00',
    ])
        ->contacts()
        ->update('c-1')
        ->optOut(true)
        ->save();

    expect($result->data->id)->toBe('c-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->phoneNumber)->toBe('+1234567890')
        ->and($result->data->formatE164)->toBe('+1234567890')
        ->and($result->data->formatInternational)->toBe('+1 234-567-890')
        ->and($result->data->formatNational)->toBe('(234) 567-890')
        ->and($result->data->formatRfc)->toBe('tel:+1-234-567-890')
        ->and($result->data->countryCode)->toBe('1')
        ->and($result->data->regionCode)->toBe('US')
        ->and($result->data->availableChannels)->toBe('sms,whatsapp')
        ->and($result->data->defaultChannel)->toBe('whatsapp')
        ->and($result->data->optOut)->toBeTrue()
        ->and($result->data->isInherited)->toBeFalse()
        ->and($result->data->updatedAt)->not->toBeNull();
});

it('contacts()->update()->defaultChannel()->save() updates a contact', function () {
    $result = sentApi(['id' => 'c-1', 'default_channel' => 'sms'])
        ->contacts()
        ->update('c-1')
        ->defaultChannel('sms')
        ->save();
    expect($result->data->id)->toBe('c-1')
        ->and($result->data->defaultChannel)->toBe('sms');
});

it('contacts()->delete() deletes a contact', function () {
    sentApi([])->contacts()->delete('c-1');
    expect(true)->toBeTrue();
});

it("contacts()->messageSummary() retrieves a contact's message summary", function () {
    $result = sentApi([
        'contact_id' => 'c-1',
        'message_count' => 12,
        'first_message_at' => '2026-01-01T00:00:00Z',
        'last_message_at' => '2026-08-01T00:00:00Z',
        'channels_used' => ['sms', 'whatsapp'],
        'channel_scores' => [
            ['channel' => 'sms', 'success_score' => 91, 'fail_score' => 9],
            ['channel' => 'whatsapp', 'success_score' => 90, 'fail_score' => 10],
        ],
    ])->contacts()->messageSummary('c-1');

    expect($result->data->contactID)->toBe('c-1')
        ->and($result->data->messageCount)->toBe(12)
        ->and($result->data->firstMessageAt)->not->toBeNull()
        ->and($result->data->lastMessageAt)->not->toBeNull()
        ->and($result->data->channelsUsed)->toBe(['sms', 'whatsapp'])
        ->and($result->data->channelScores[0]->channel)->toBe('sms')
        ->and($result->data->channelScores[0]->successScore)->toBe(91)
        ->and($result->data->channelScores[0]->failScore)->toBe(9)
        ->and($result->data->channelScores[1]->channel)->toBe('whatsapp')
        ->and($result->data->channelScores[1]->successScore)->toBe(90)
        ->and($result->data->channelScores[1]->failScore)->toBe(10);
});
