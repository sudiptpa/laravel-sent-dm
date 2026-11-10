<?php

declare(strict_types=1);

use SentDm\Channels\Voice\APIResponseOfListOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceCallbackTest;
use SentDm\Channels\Voice\APIResponseOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceNumberCreated;
use SentDm\Channels\Voice\APIResponseOfVoiceSecret;
use SentDm\Channels\Voice\VoiceUpdateParams\Status;
use SentDm\Core\FileParam;
use SentDm\Voice\Tokens\APIResponseOfVoiceToken;
use Sujip\SentDm\Resources\Voice;

it('channels()->get() returns channel state', function () {
    $result = sentApi([
        'customer_id' => 'cust-1',
        'sms' => [[
            'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
            'numbers' => [['sender_value' => '+12125550123', 'area_code' => '212', 'status' => 'ACTIVE']],
            'status' => 'ACTIVE', 'reason_code' => 'CHANNEL_001', 'reason' => 'Waiting on compliance',
            'note' => 'Ready to send',
            'compliance' => ['brand' => ['legal_name' => 'Acme']],
        ]],
        'whatsapp' => [
            'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
            'owner_business_id' => 'biz-1', 'status' => 'CONNECTED',
            'reason_code' => 'CHANNEL_002', 'reason' => 'Connect a phone number',
        ],
        'rcs' => [
            'id' => 'rcs-1', 'status' => 'PENDING', 'reason_code' => 'CHANNEL_009',
            'reason' => 'Under review', 'phone_number' => '+12125550123',
        ],
        'mms' => [[
            'country' => 'US', 'number_type' => 'LONG_CODE', 'sender_value' => '+12125550123',
            'status' => 'ACTIVE',
        ]],
        'voice' => [[
            'number' => '+12125550100', 'status' => 'ACTIVE', 'default_for_app_calls' => true,
            'callback_url' => 'https://example.com/voice',
            'created_at' => '2026-10-03T00:00:00+00:00',
            'updated_at' => '2026-10-03T00:01:00+00:00',
        ]],
    ])->channels()->get();

    $state = $result->data;

    expect($state->customerID)->toBe('cust-1')
        ->and($state->sms[0]->country)->toBe('US')
        ->and($state->sms[0]->numberType)->toBe('TEN_DLC')
        ->and($state->sms[0]->numbers[0]->senderValue)->toBe('+12125550123')
        ->and($state->sms[0]->numbers[0]->areaCode)->toBe('212')
        ->and($state->sms[0]->numbers[0]->status)->toBe('ACTIVE')
        ->and($state->sms[0]->status)->toBe('ACTIVE')
        ->and($state->sms[0]->reasonCode)->toBe('CHANNEL_001')
        ->and($state->sms[0]->reason)->toBe('Waiting on compliance')
        ->and($state->sms[0]->compliance->brand)->toBe(['legal_name' => 'Acme'])
        ->and($state->whatsapp->wabaID)->toBe('waba-1')
        ->and($state->whatsapp->phoneNumberID)->toBe('phone-1')
        ->and($state->whatsapp->solutionID)->toBe('sol-1')
        ->and($state->whatsapp->ownerBusinessID)->toBe('biz-1')
        ->and($state->whatsapp->status)->toBe('CONNECTED')
        ->and($state->whatsapp->reasonCode)->toBe('CHANNEL_002')
        ->and($state->rcs->id)->toBe('rcs-1')
        ->and($state->rcs->status)->toBe('PENDING')
        ->and($state->rcs->phoneNumber)->toBe('+12125550123')
        ->and($state->mms[0]->country)->toBe('US')
        ->and($state->mms[0]->numberType)->toBe('LONG_CODE')
        ->and($state->mms[0]->senderValue)->toBe('+12125550123')
        ->and($state->voice[0]->number)->toBe('+12125550100')
        ->and($state->voice[0]->status)->toBe('ACTIVE')
        ->and($state->voice[0]->defaultForAppCalls)->toBeTrue()
        ->and($state->voice[0]->callbackURL)->toBe('https://example.com/voice');
});

it('channels()->smsMarkets() lists SMS markets', function () {
    $result = sentApiList([[
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'numbers' => [['sender_value' => '+12125550123', 'area_code' => '212', 'status' => 'ACTIVE']],
        'status' => 'ACTIVE', 'reason_code' => 'CHANNEL_001', 'reason' => 'Waiting on compliance',
        'note' => 'Ready to send',
        'compliance' => ['brand' => ['legal_name' => 'Acme']],
    ]])->channels()->smsMarkets();

    expect($result->data[0]->country)->toBe('US')
        ->and($result->data[0]->numberType)->toBe('TEN_DLC')
        ->and($result->data[0]->numbers[0]->senderValue)->toBe('+12125550123')
        ->and($result->data[0]->status)->toBe('ACTIVE')
        ->and($result->data[0]->reasonCode)->toBe('CHANNEL_001')
        ->and($result->data[0]->reason)->toBe('Waiting on compliance')
        ->and($result->data[0]->compliance->brand)->toBe(['legal_name' => 'Acme']);
});

it('channels()->findSmsMarket() retrieves an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => 'Acme',
        'status' => 'ACTIVE', 'note' => 'Ready to send',
        'compliance' => ['brand' => ['legal_name' => 'Acme']],
    ])
        ->channels()
        ->findSmsMarket('US', 'TEN_DLC');

    expect($result->data->country)->toBe('US')
        ->and($result->data->numberType)->toBe('TEN_DLC')
        ->and($result->data->senderValue)->toBe('Acme')
        ->and($result->data->status)->toBe('ACTIVE')
        ->and($result->data->compliance->brand)->toBe(['legal_name' => 'Acme']);
});

it('channels()->addSmsMarket() adds an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'status' => 'PENDING', 'note' => 'Waiting for review', 'compliance' => null,
    ])
        ->channels()
        ->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC']);

    expect($result->data->country)->toBe('US')
        ->and($result->data->numberType)->toBe('TEN_DLC')
        ->and($result->data->status)->toBe('PENDING');
});

it('channels()->addSmsMarket() with a FileParam sends a multipart request with renamed fields', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'XK', 'number_type' => 'ALPHANUMERIC']);

    $sent->channels()->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'sender_value' => 'EXAMPLE',
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data')
        ->and($captured->body)->toContain('name="numberType"')
        ->and($captured->body)->toContain('name="senderValue"')
        ->and($captured->body)->not->toContain('name="number_type"')
        ->and($captured->body)->not->toContain('name="sender_value"');
});

it('channels()->addSmsMarket() throws when compliance is combined with a document', function () {
    sentApi()->channels()->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'compliance' => ['brand' => ['inherit' => true]],
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);
})->throws(InvalidArgumentException::class, 'compliance is not supported together with a document upload');

it('channels()->updateSmsMarket() updates an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => 'Acme',
        'status' => 'ACTIVE', 'note' => 'Ready to send',
        'compliance' => ['brand' => ['legal_name' => 'Test Co']],
    ])
        ->channels()
        ->updateSmsMarket('US', 'TEN_DLC', ['sandbox' => true]);

    expect($result->data->country)->toBe('US')
        ->and($result->data->numberType)->toBe('TEN_DLC')
        ->and($result->data->senderValue)->toBe('Acme')
        ->and($result->data->status)->toBe('ACTIVE')
        ->and($result->data->compliance->brand)->toBe(['legal_name' => 'Test Co']);
});

it('channels()->smsMarket() builder adds an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'status' => 'PENDING', 'note' => 'Waiting for review', 'compliance' => null,
    ])
        ->channels()
        ->smsMarket()
        ->country('US')
        ->numberType('TEN_DLC')
        ->save();

    expect($result->data->country)->toBe('US')
        ->and($result->data->numberType)->toBe('TEN_DLC')
        ->and($result->data->status)->toBe('PENDING');
});

it('channels()->smsMarket() builder throws without a numberType', function () {
    sentApi()->channels()->smsMarket()->country('US')->save();
})->throws(InvalidArgumentException::class, 'A number type is required');

it('channels()->smsMarket() builder sends areaCodes', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'TEN_DLC']);

    $sent->channels()->smsMarket()
        ->country('US')
        ->numberType('TEN_DLC')
        ->areaCodes(['212', '646'])
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['area_codes'])->toBe(['212', '646']);
});

it('channels()->smsMarket() builder sends numbers', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'LOCAL']);

    $sent->channels()->smsMarket()
        ->country('US')
        ->numberType('LOCAL')
        ->numbers([['area_code' => '212', 'quantity' => 2]])
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['numbers'])->toBe([['area_code' => '212', 'quantity' => 2]]);
});

it('channels()->smsMarket() builder rejects numbers with areaCodes', function () {
    sentApi()->channels()->smsMarket()
        ->country('US')
        ->numberType('LOCAL')
        ->areaCodes(['212'])
        ->numbers([['area_code' => '212', 'quantity' => 2]])
        ->save();
})->throws(InvalidArgumentException::class, 'numbers() and areaCodes() cannot be used together');

it('channels()->smsMarket() builder attach() sends a multipart request with renamed fields', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'XK', 'number_type' => 'ALPHANUMERIC']);

    $sent->channels()->smsMarket()
        ->country('XK')
        ->numberType('ALPHANUMERIC')
        ->senderValue('EXAMPLE')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data')
        ->and($captured->body)->toContain('name="numberType"')
        ->and($captured->body)->toContain('name="senderValue"');
});

it('channels()->smsMarket() builder throws when compliance is combined with a document', function () {
    sentApi()->channels()->smsMarket()
        ->country('XK')
        ->numberType('ALPHANUMERIC')
        ->compliance(['brand' => ['inherit' => true]])
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();
})->throws(InvalidArgumentException::class, 'compliance is not supported together with a document upload');

it('channels()->updateSmsMarketBuilder() updates an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => 'Acme',
        'status' => 'ACTIVE', 'note' => 'Ready to send', 'compliance' => ['brand' => ['legal_name' => 'Test Co']],
    ])
        ->channels()
        ->updateSmsMarketBuilder('US', 'TEN_DLC')
        ->compliance(['brand' => ['legal_name' => 'Test Co']])
        ->save();

    expect($result->data->country)->toBe('US')
        ->and($result->data->numberType)->toBe('TEN_DLC')
        ->and($result->data->senderValue)->toBe('Acme')
        ->and($result->data->compliance->brand)->toBe(['legal_name' => 'Test Co']);
});

it('channels()->updateSmsMarketBuilder() throws when country() is called, the path decides the market', function () {
    sentApi()->channels()->updateSmsMarketBuilder('US', 'TEN_DLC')->country('GB')->save();
})->throws(InvalidArgumentException::class, 'country() and numberType() are not supported on update()');

it('channels()->updateSmsMarketBuilder() throws when numberType() is called, the path decides the market', function () {
    sentApi()->channels()->updateSmsMarketBuilder('US', 'TEN_DLC')->numberType('LOCAL')->save();
})->throws(InvalidArgumentException::class, 'country() and numberType() are not supported on update()');

it('channels()->smsMarket() builder throws without a country', function () {
    sentApi()->channels()->smsMarket()->numberType('TEN_DLC')->save();
})->throws(InvalidArgumentException::class, 'A country is required');

it('channels()->updateSmsMarketBuilder() throws when senderValue() is called, the API rejects it there', function () {
    sentApi()->channels()->updateSmsMarketBuilder('US', 'TEN_DLC')->senderValue('Acme')->save();
})->throws(InvalidArgumentException::class, 'senderValue(), areaCodes(), and numbers() are not supported on update()');

it('channels()->updateSmsMarketBuilder() throws when areaCodes() is called, the API rejects it there', function () {
    sentApi()->channels()->updateSmsMarketBuilder('US', 'TEN_DLC')->areaCodes(['212'])->save();
})->throws(InvalidArgumentException::class, 'senderValue(), areaCodes(), and numbers() are not supported on update()');

it('channels()->updateSmsMarketBuilder() throws when numbers() is called, the API rejects it there', function () {
    sentApi()->channels()->updateSmsMarketBuilder('US', 'TEN_DLC')
        ->numbers([['area_code' => '212', 'quantity' => 2]])
        ->save();
})->throws(InvalidArgumentException::class, 'senderValue(), areaCodes(), and numbers() are not supported on update()');

it('channels()->updateSmsMarketBuilder() throws when attach() is used, update does not support multipart', function () {
    sentApi()->channels()->updateSmsMarketBuilder('US', 'TEN_DLC')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();
})->throws(InvalidArgumentException::class, 'attach() is not supported on update()');

it('channels()->addSmsMarket() sends the typed create body with a normalized number type', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'LOCAL']);

    $sent->channels()->addSmsMarket([
        'country' => 'US',
        'number_type' => 'LOCAL',
        'numbers' => [['areaCode' => '212', 'quantity' => 2]],
        'sandbox' => true,
    ], 'idem-1');

    expect($captured->uri)->toContain('/v3/channels/sms')
        ->and(json_decode((string) $captured->body, true))->toBe([
            'number_type' => 'LOCAL',
            'country' => 'US',
            'numbers' => [['area_code' => '212', 'quantity' => 2]],
            'sandbox' => true,
        ])
        ->and($captured->headers['Idempotency-Key'] ?? [])->toBe(['idem-1']);
});

it('channels()->addSmsMarket() rejects an unknown number type', function () {
    sentApi()->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'NOPE']);
})->throws(InvalidArgumentException::class, 'A valid number_type is required');

it('channels()->addSmsMarket() with a FileParam keeps an explicit sandbox flag and the profile header', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'XK', 'number_type' => 'ALPHANUMERIC']);

    $sent->channels()->profile('prof-1')->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'sandbox' => true,
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);

    expect($captured->body)->toContain('name="sandbox"')
        ->and($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);
});

it('channels()->updateSmsMarket() sends only the fields given, to the market path', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'TEN_DLC']);

    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['compliance' => ['brand' => ['contactPhone' => '+12125550123']]], 'idem-1');

    expect($captured->uri)->toContain('/v3/channels/sms/US/TEN_DLC')
        ->and(json_decode((string) $captured->body, true))->toBe(['compliance' => ['brand' => ['contact_phone' => '+12125550123']]]);
});

it('channels()->addSmsMarket() accepts a lower-case number type and sends it upper-case', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'LOCAL']);

    $sent->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'local']);

    expect(json_decode((string) $captured->body, true)['number_type'])->toBe('LOCAL');
});

it('channels()->updateSmsMarket() sends an explicit null compliance to clear it', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'TEN_DLC']);

    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['compliance' => null, 'sandbox' => true]);

    expect(json_decode((string) $captured->body, true))->toBe(['compliance' => null, 'sandbox' => true]);
});

it('channels()->updateSmsMarket() leaves compliance out of the body when the key is absent', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'TEN_DLC']);

    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['sandbox' => true]);

    expect(json_decode((string) $captured->body, true))->toBe(['sandbox' => true]);
});

it('channels()->updateSmsMarket() keeps nested nulls so a single field can be cleared', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US', 'number_type' => 'TEN_DLC']);

    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['compliance' => ['brand' => ['contactPhone' => null, 'contactEmail' => 'a@b.co']]]);

    expect(json_decode((string) $captured->body, true))->toBe(['compliance' => ['brand' => ['contact_email' => 'a@b.co', 'contact_phone' => null]]]);
});

it('channels()->addWhatsapp() adds a WhatsApp channel', function () {
    $result = sentApi([
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
        'owner_business_id' => 'biz-1', 'status' => 'PENDING',
    ])->channels()->addWhatsapp(['waba_id' => 'waba-1']);

    expect($result->data->wabaID)->toBe('waba-1')
        ->and($result->data->phoneNumberID)->toBe('phone-1')
        ->and($result->data->solutionID)->toBe('sol-1')
        ->and($result->data->ownerBusinessID)->toBe('biz-1')
        ->and($result->data->status)->toBe('PENDING');
});

it('channels()->whatsapp() builder adds a WhatsApp channel', function () {
    $result = sentApi([
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
        'owner_business_id' => 'biz-1', 'status' => 'PENDING',
    ])->channels()->whatsapp()->wabaId('waba-1')->phoneNumberId('phone-1')->save();

    expect($result->data->wabaID)->toBe('waba-1')
        ->and($result->data->phoneNumberID)->toBe('phone-1')
        ->and($result->data->solutionID)->toBe('sol-1')
        ->and($result->data->ownerBusinessID)->toBe('biz-1')
        ->and($result->data->status)->toBe('PENDING');
});

it('channels()->whatsapp() builder throws without a wabaId', function () {
    sentApi()->channels()->whatsapp()->save();
})->throws(InvalidArgumentException::class, 'A WABA id is required');

it('channels()->addWhatsapp() sends the waba id, phone number id and sandbox flag', function () {
    [$captured, $sent] = capturedSentHeaders(['waba_id' => 'waba-1']);

    $sent->channels()->addWhatsapp(['waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'sandbox' => true], 'idem-1');

    expect($captured->uri)->toContain('/v3/channels/whatsapp')
        ->and(json_decode((string) $captured->body, true))->toBe([
            'phone_number_id' => 'phone-1',
            'sandbox' => true,
            'waba_id' => 'waba-1',
        ])
        ->and($captured->headers['Idempotency-Key'] ?? [])->toBe(['idem-1']);
});

it('channels()->addWhatsapp() throws without a waba id', function () {
    sentApi()->channels()->addWhatsapp(['phone_number_id' => 'phone-1']);
})->throws(InvalidArgumentException::class, 'Missing required field for addWhatsapp(): waba_id');

it('channels()->addRcs() adds an RCS agent', function () {
    $result = sentApi([
        'id' => 'rcs-1',
        'status' => 'PENDING',
        'phone_number' => '+12125550123',
        'display_name' => 'Acme',
        'description' => 'Home services booking agent',
        'agent_use_case' => 'NOTIFICATIONS',
        'brand_name' => 'Acme',
        'privacy_policy_url' => 'https://example.com/privacy',
        'terms_and_conditions_url' => 'https://example.com/terms',
        'website_url' => 'https://example.com',
        'brand_color' => '#838FB6',
        'brand_phone_number' => '+12125550123',
        'customer_support_phone_number' => '+12125550124',
        'brand_email' => 'hello@example.com',
        'customer_support_email' => 'support@example.com',
        'contact_name_and_title' => 'Jane Smith, Head of Marketing',
        'company_ein' => '12-3456789',
        'entity_type' => 'LLC',
        'official_address' => ['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'],
        'brief_company_description' => 'Home services booking platform',
        'opt_in_process_description' => 'Users opt in via the website sign-up form',
        'start_message' => 'Welcome!',
        'help_message' => 'For assistance call +12125550123',
        'stop_message' => 'You have been unsubscribed.',
        'sample_messages' => ['Hi there'],
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])
        ->channels()
        ->addRcs(fullRcsBody(['brand_name' => 'Acme']));

    expect($result->id)->toBe('rcs-1')
        ->and($result->status)->toBe('PENDING')
        ->and($result->phoneNumber)->toBe('+12125550123')
        ->and($result->displayName)->toBe('Acme')
        ->and($result->description)->toBe('Home services booking agent')
        ->and($result->agentUseCase)->toBe('NOTIFICATIONS')
        ->and($result->brandName)->toBe('Acme')
        ->and($result->privacyPolicyUrl)->toBe('https://example.com/privacy')
        ->and($result->termsAndConditionsUrl)->toBe('https://example.com/terms')
        ->and($result->websiteUrl)->toBe('https://example.com')
        ->and($result->brandColor)->toBe('#838FB6')
        ->and($result->brandPhoneNumber)->toBe('+12125550123')
        ->and($result->customerSupportPhoneNumber)->toBe('+12125550124')
        ->and($result->brandEmail)->toBe('hello@example.com')
        ->and($result->customerSupportEmail)->toBe('support@example.com')
        ->and($result->contactNameAndTitle)->toBe('Jane Smith, Head of Marketing')
        ->and($result->companyEin)->toBe('12-3456789')
        ->and($result->entityType)->toBe('LLC')
        ->and($result->officialAddress)->toBe(['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'])
        ->and($result->briefCompanyDescription)->toBe('Home services booking platform')
        ->and($result->optInProcessDescription)->toBe('Users opt in via the website sign-up form')
        ->and($result->startMessage)->toBe('Welcome!')
        ->and($result->helpMessage)->toBe('For assistance call +12125550123')
        ->and($result->stopMessage)->toBe('You have been unsubscribed.')
        ->and($result->sampleMessages)->toBe(['Hi there']);
});

it('channels()->addRcs() throws when required fields are missing', function () {
    sentApi()->channels()->addRcs(['brand_name' => 'Acme']);
})->throws(InvalidArgumentException::class, 'Missing required field(s) for addRcs()');

it('channels()->rcs() builder adds an RCS agent', function () {
    $result = sentApi([
        'id' => 'rcs-1',
        'status' => 'PENDING',
        'phone_number' => '+12125550123',
        'display_name' => 'Acme',
        'description' => 'Home services booking agent',
        'agent_use_case' => 'NOTIFICATIONS',
        'brand_name' => 'Acme',
        'privacy_policy_url' => 'https://example.com/privacy',
        'terms_and_conditions_url' => 'https://example.com/terms',
        'website_url' => 'https://example.com',
        'brand_color' => '#838FB6',
        'brand_phone_number' => '+12125550123',
        'customer_support_phone_number' => '+12125550124',
        'brand_email' => 'hello@example.com',
        'customer_support_email' => 'support@example.com',
        'contact_name_and_title' => 'Jane Smith, Head of Marketing',
        'company_ein' => '12-3456789',
        'entity_type' => 'LLC',
        'official_address' => ['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'],
        'brief_company_description' => 'Home services booking platform',
        'opt_in_process_description' => 'Users opt in via the website sign-up form',
        'start_message' => 'Welcome!',
        'help_message' => 'For assistance call +12125550123',
        'stop_message' => 'You have been unsubscribed.',
        'sample_messages' => ['Hi there'],
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])
        ->channels()
        ->rcs()
        ->displayName('Acme')
        ->description('Home services booking agent')
        ->agentUseCase('NOTIFICATIONS')
        ->brandName('Acme')
        ->privacyPolicyUrl('https://example.com/privacy')
        ->termsAndConditionsUrl('https://example.com/terms')
        ->websiteUrl('https://example.com')
        ->brandColor('#838FB6')
        ->logoUrl('https://example.com/logo.png')
        ->bannerUrl('https://example.com/banner.png')
        ->brandPhoneNumber('+12125550123')
        ->customerSupportPhoneNumber('+12125550124')
        ->brandEmail('hello@example.com')
        ->customerSupportEmail('support@example.com')
        ->contactNameAndTitle('Jane Smith, Head of Marketing')
        ->companyEin('12-3456789')
        ->entityType('LLC')
        ->officialAddress(['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'])
        ->briefCompanyDescription('Home services booking platform')
        ->optInProcessDescription('Users opt in via the website sign-up form')
        ->startMessage('Welcome!')
        ->helpMessage('For assistance call +12125550123')
        ->stopMessage('You have been unsubscribed.')
        ->sampleMessages(['Hi there'])
        ->save();

    expect($result->id)->toBe('rcs-1')
        ->and($result->status)->toBe('PENDING')
        ->and($result->displayName)->toBe('Acme')
        ->and($result->agentUseCase)->toBe('NOTIFICATIONS')
        ->and($result->officialAddress)->toBe(['street' => '1 Example St', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'country' => 'US'])
        ->and($result->sampleMessages)->toBe(['Hi there']);
});

it('channels()->rcs() builder throws when required fields are missing', function () {
    sentApi()->channels()->rcs()->brandName('Acme')->save();
})->throws(InvalidArgumentException::class, 'Missing required field(s) for addRcs()');

it('channels()->rcs() builder accepts the optional fields', function () {
    $body = fullRcsBody();
    [$captured, $sent] = capturedSentHeaders($body);

    $sent->channels()->rcs()
        ->displayName($body['display_name'])
        ->description($body['description'])
        ->agentUseCase($body['agent_use_case'])
        ->brandName($body['brand_name'])
        ->privacyPolicyUrl($body['privacy_policy_url'])
        ->termsAndConditionsUrl($body['terms_and_conditions_url'])
        ->websiteUrl($body['website_url'])
        ->brandColor($body['brand_color'])
        ->logoUrl($body['logo_url'])
        ->bannerUrl($body['banner_url'])
        ->brandPhoneNumber($body['brand_phone_number'])
        ->customerSupportPhoneNumber($body['customer_support_phone_number'])
        ->brandEmail($body['brand_email'])
        ->customerSupportEmail($body['customer_support_email'])
        ->contactNameAndTitle($body['contact_name_and_title'])
        ->companyEin($body['company_ein'])
        ->entityType($body['entity_type'])
        ->officialAddress($body['official_address'])
        ->briefCompanyDescription($body['brief_company_description'])
        ->optInProcessDescription($body['opt_in_process_description'])
        ->startMessage($body['start_message'])
        ->helpMessage($body['help_message'])
        ->stopMessage($body['stop_message'])
        ->sampleMessages($body['sample_messages'])
        ->optInScreenshotUrl('https://example.com/screenshot.png')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['opt_in_screenshot_url'])->toBe('https://example.com/screenshot.png');
});

it('channels()->voice() returns a Voice resource', function () {
    expect(sentApi()->channels()->voice())->toBeInstanceOf(Voice::class);
});

it('channels()->voice()->create() enables a voice number', function () {
    $result = sentApi([
        'number' => '+12125550100',
        'status' => 'ACTIVE',
        'callback_url' => 'https://example.com/voice',
        'default_for_app_calls' => true,
        'callback_secret' => 'voice_secret_1',
        'created_at' => '2026-10-01T00:00:00Z',
        'updated_at' => '2026-10-01T00:00:00Z',
    ])->channels()->voice()->create('https://example.com/voice', number: '+12125550100');

    expect($result)->toBeInstanceOf(APIResponseOfVoiceNumberCreated::class)
        ->and($result->data->number)->toBe('+12125550100')
        ->and($result->data->callbackSecret)->toBe('voice_secret_1');
});

it('channels()->voice()->retrieve() reads a voice number', function () {
    $result = sentApi([
        'number' => '+12125550100',
        'status' => 'ACTIVE',
        'callback_url' => 'https://example.com/voice',
        'default_for_app_calls' => true,
        'created_at' => '2026-10-01T00:00:00Z',
        'updated_at' => '2026-10-01T00:00:00Z',
    ])->channels()->voice()->retrieve('+12125550100');

    expect($result)->toBeInstanceOf(APIResponseOfVoiceNumber::class)
        ->and($result->data->number)->toBe('+12125550100')
        ->and($result->data->callbackURL)->toBe('https://example.com/voice');
});

it('channels()->voice() lets the SDK encode plus signs in number paths', function () {
    [$captured, $sent] = capturedSentHeaders([
        'number' => '+12125550100',
        'status' => 'ACTIVE',
    ]);

    $sent->channels()->voice()->retrieve('+12125550100');

    expect($captured->uri)->toContain('/v3/channels/voice/%2B12125550100');
});

it('channels()->voice()->update() updates a voice number', function () {
    $result = sentApi([
        'number' => '+12125550100',
        'status' => 'INACTIVE',
        'callback_url' => 'https://example.com/new-voice',
        'default_for_app_calls' => false,
    ])->channels()->voice()->update('+12125550100', callbackUrl: 'https://example.com/new-voice', status: Status::INACTIVE);

    expect($result)->toBeInstanceOf(APIResponseOfVoiceNumber::class)
        ->and($result->data->status)->toBe('INACTIVE')
        ->and($result->data->callbackURL)->toBe('https://example.com/new-voice');
});

it('channels()->voice()->update() accepts lowercase status names', function () {
    [$captured, $sent] = capturedSentHeaders(['number' => '+12125550100']);
    $sent->channels()->voice()->update('+12125550100', status: 'active');

    $body = json_decode((string) $captured->body, true);
    expect($body['status'])->toBe('ACTIVE');
});

it('channels()->voice()->update(unbindAgent: true) sends a literal null agent_id', function () {
    [$captured, $sent] = capturedSentHeaders(['number' => '+12125550100', 'routes_to' => 'callback']);
    $result = $sent->channels()->voice()->profile('profile_1')->update('+12125550100', callbackUrl: 'https://example.com/voice', unbindAgent: true);

    $body = json_decode((string) $captured->body, true);
    expect($body)->toHaveKey('agent_id')
        ->and($body['agent_id'])->toBeNull()
        ->and($body['callback_url'])->toBe('https://example.com/voice')
        ->and($captured->uri)->toContain('/v3/channels/voice/%2B12125550100')
        ->and($captured->headers['x-profile-id'][0] ?? null)->toBe('profile_1')
        ->and($result)->toBeInstanceOf(APIResponseOfVoiceNumber::class)
        ->and($result->data->routesTo)->toBe('callback');
});

it('channels()->voice()->list() lists voice numbers', function () {
    $result = sentApiList([[
        'number' => '+12125550100',
        'status' => 'ACTIVE',
        'default_for_app_calls' => true,
    ]])->channels()->voice()->list();

    expect($result)->toBeInstanceOf(APIResponseOfListOfVoiceNumber::class)
        ->and($result->data[0]->number)->toBe('+12125550100')
        ->and($result->data[0]->defaultForAppCalls)->toBeTrue();
});

it('channels()->voice()->createToken() creates a voice token', function () {
    $result = sentApi([
        'token' => 'voice_token_1',
        'identity' => 'agent-1',
        'number' => '+12125550100',
        'expires_at' => '2026-10-01T00:10:00Z',
    ])->channels()->voice()->createToken('agent-1', number: '+12125550100', ttl: 600);

    expect($result)->toBeInstanceOf(APIResponseOfVoiceToken::class)
        ->and($result->data->token)->toBe('voice_token_1')
        ->and($result->data->identity)->toBe('agent-1');
});

it('channels()->voice()->rotateSecret() returns the new voice callback secret', function () {
    $result = sentApi(['callback_secret' => 'voice_secret_2'])
        ->channels()
        ->voice()
        ->rotateSecret('+12125550100');

    expect($result)->toBeInstanceOf(APIResponseOfVoiceSecret::class)
        ->and($result->data->callbackSecret)->toBe('voice_secret_2');
});

it('channels()->voice()->test() returns the callback test result', function () {
    $result = sentApi([
        'outcome' => 'ok',
        'call_id' => 'call_1',
        'request' => ['url' => 'https://example.com/voice'],
        'response' => ['status_code' => 200, 'body' => '{}'],
    ])->channels()->voice()->test('+12125550100');

    expect($result)->toBeInstanceOf(APIResponseOfVoiceCallbackTest::class)
        ->and($result->data->outcome)->toBe('ok')
        ->and($result->data->callID)->toBe('call_1');
});

it('channels() reads go to their method and path', function () {
    [$captured, $sent] = capturedSentHeaders(['customer_id' => 'c']);
    $path = fn (): string => (string) parse_url((string) $captured->uri, PHP_URL_PATH);

    $sent->channels()->get();
    expect([$captured->method, $path()])->toBe(['GET', '/v3/channels']);

    $sent->channels()->smsMarkets();
    expect([$captured->method, $path()])->toBe(['GET', '/v3/channels/sms']);

    $sent->channels()->findSmsMarket('US', 'TEN_DLC');
    expect([$captured->method, $path()])->toBe(['GET', '/v3/channels/sms/US/TEN_DLC']);
});

it('channels()->findSmsMarket() encodes the country and type in the path', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);

    $sent->channels()->findSmsMarket('a/b', 'c d');

    expect($captured->uri)->toContain('/v3/channels/sms/a%2Fb/c%20d');
});

it('channels()->profile() scopes the reads to a child profile', function () {
    [$captured, $sent] = capturedSentHeaders(['customer_id' => 'c']);
    $scoped = $sent->channels()->profile('prof-1');

    foreach ([fn () => $scoped->get(), fn () => $scoped->smsMarkets(), fn () => $scoped->findSmsMarket('US', 'TEN_DLC')] as $call) {
        $captured->headers = null;
        $call();
        expect($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);
    }
});

it('channels()->get() leaves a field the API omits unset', function () {
    $state = sentApi([
        'customer_id' => 'cust-1',
        'whatsapp' => ['waba_id' => 'waba-1', 'status' => 'ACTIVE'],
    ])->channels()->get()->data;

    expect($state->whatsapp->status)->toBe('ACTIVE')
        ->and(isset($state->whatsapp->reason))->toBeFalse()
        ->and($state->whatsapp->reason ?? null)->toBeNull()
        ->and(isset($state->sms))->toBeFalse();
});

it('channels() SMS create and update go to their method and path', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);
    $path = fn (): string => (string) parse_url((string) $captured->uri, PHP_URL_PATH);

    $sent->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'LOCAL']);
    expect([$captured->method, $path()])->toBe(['POST', '/v3/channels/sms']);

    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['sandbox' => true]);
    expect([$captured->method, $path()])->toBe(['PATCH', '/v3/channels/sms/US/TEN_DLC']);

    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['compliance' => ['brand' => null]]);
    expect([$captured->method, $path()])->toBe(['PATCH', '/v3/channels/sms/US/TEN_DLC']);
});

it('channels()->addSmsMarket() sends numbers and area codes under their wire names', function (array $numbers) {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);

    $sent->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'LOCAL', 'numbers' => $numbers]);

    expect(json_decode((string) $captured->body, true)['numbers'])->toBe([['area_code' => '212', 'quantity' => 2]]);
})->with([
    'camelCase' => [[['areaCode' => '212', 'quantity' => 2]]],
    'snake_case' => [[['area_code' => '212', 'quantity' => 2]]],
]);

it('channels()->addSmsMarket() sends area codes as a list', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);

    $sent->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'LOCAL', 'area_codes' => ['212', '646']]);

    expect(json_decode((string) $captured->body, true)['area_codes'])->toBe(['212', '646']);
});

it('channels() WhatsApp create goes to its method and path', function () {
    [$captured, $sent] = capturedSentHeaders(['waba_id' => 'waba-1']);

    $sent->channels()->addWhatsapp(['waba_id' => 'waba-1']);

    expect([$captured->method, parse_url((string) $captured->uri, PHP_URL_PATH)])->toBe(['POST', '/v3/channels/whatsapp']);
});

it('channels()->profile()->addWhatsapp() scopes the request to a child profile', function () {
    [$captured, $sent] = capturedSentHeaders(['waba_id' => 'waba-1']);

    $sent->channels()->profile('prof-1')->addWhatsapp(['waba_id' => 'waba-1']);

    expect($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);
});

it('channels()->addWhatsapp() leaves sandbox out of the body when it is off', function () {
    [$captured, $sent] = capturedSentHeaders(['waba_id' => 'waba-1']);

    $sent->channels()->addWhatsapp(['waba_id' => 'waba-1', 'sandbox' => false]);

    expect(array_key_exists('sandbox', json_decode((string) $captured->body, true)))->toBeFalse();
});
