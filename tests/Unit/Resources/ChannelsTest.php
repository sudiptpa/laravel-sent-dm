<?php

declare(strict_types=1);

use SentDm\Channels\Voice\APIResponseOfListOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceCallbackTest;
use SentDm\Channels\Voice\APIResponseOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceNumberCreated;
use SentDm\Channels\Voice\APIResponseOfVoiceSecret;
use SentDm\Channels\Voice\APIResponseOfVoiceToken;
use SentDm\Channels\Voice\VoiceUpdateParams\Status;
use SentDm\Core\FileParam;
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

    expect($result->customerId)->toBe('cust-1')
        ->and($result->sms[0]->country)->toBe('US')
        ->and($result->sms[0]->numberType)->toBe('TEN_DLC')
        ->and($result->sms[0]->numbers[0]->senderValue)->toBe('+12125550123')
        ->and($result->sms[0]->numbers[0]->areaCode)->toBe('212')
        ->and($result->sms[0]->numbers[0]->status)->toBe('ACTIVE')
        ->and($result->sms[0]->status)->toBe('ACTIVE')
        ->and($result->sms[0]->reasonCode)->toBe('CHANNEL_001')
        ->and($result->sms[0]->reason)->toBe('Waiting on compliance')
        ->and($result->sms[0]->note)->toBe('Ready to send')
        ->and($result->sms[0]->compliance)->toBe(['brand' => ['legal_name' => 'Acme']])
        ->and($result->whatsapp->wabaId)->toBe('waba-1')
        ->and($result->whatsapp->phoneNumberId)->toBe('phone-1')
        ->and($result->whatsapp->solutionId)->toBe('sol-1')
        ->and($result->whatsapp->ownerBusinessId)->toBe('biz-1')
        ->and($result->whatsapp->status)->toBe('CONNECTED')
        ->and($result->whatsapp->reasonCode)->toBe('CHANNEL_002')
        ->and($result->whatsapp->reason)->toBe('Connect a phone number')
        ->and($result->rcs->id)->toBe('rcs-1')
        ->and($result->rcs->status)->toBe('PENDING')
        ->and($result->rcs->reasonCode)->toBe('CHANNEL_009')
        ->and($result->rcs->reason)->toBe('Under review')
        ->and($result->rcs->phoneNumber)->toBe('+12125550123')
        ->and($result->mms[0]->country)->toBe('US')
        ->and($result->mms[0]->numberType)->toBe('LONG_CODE')
        ->and($result->mms[0]->senderValue)->toBe('+12125550123')
        ->and($result->mms[0]->status)->toBe('ACTIVE')
        ->and($result->voice[0]->number)->toBe('+12125550100')
        ->and($result->voice[0]->status)->toBe('ACTIVE')
        ->and($result->voice[0]->defaultForAppCalls)->toBeTrue()
        ->and($result->voice[0]->callbackUrl)->toBe('https://example.com/voice')
        ->and($result->voice[0]->createdAt)->toBe('2026-10-03T00:00:00+00:00')
        ->and($result->voice[0]->updatedAt)->toBe('2026-10-03T00:01:00+00:00');
});

it('channels()->smsMarkets() lists SMS markets', function () {
    $result = sentApiList([[
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'numbers' => [['sender_value' => '+12125550123', 'area_code' => '212', 'status' => 'ACTIVE']],
        'status' => 'ACTIVE', 'reason_code' => 'CHANNEL_001', 'reason' => 'Waiting on compliance',
        'note' => 'Ready to send',
        'compliance' => ['brand' => ['legal_name' => 'Acme']],
    ]])->channels()->smsMarkets();

    expect($result[0]->country)->toBe('US')
        ->and($result[0]->numberType)->toBe('TEN_DLC')
        ->and($result[0]->numbers[0]->senderValue)->toBe('+12125550123')
        ->and($result[0]->status)->toBe('ACTIVE')
        ->and($result[0]->reasonCode)->toBe('CHANNEL_001')
        ->and($result[0]->reason)->toBe('Waiting on compliance')
        ->and($result[0]->note)->toBe('Ready to send')
        ->and($result[0]->compliance)->toBe(['brand' => ['legal_name' => 'Acme']]);
});

it('channels()->findSmsMarket() retrieves an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => 'Acme',
        'status' => 'ACTIVE', 'note' => 'Ready to send',
        'compliance' => ['brand' => ['legal_name' => 'Acme']],
    ])
        ->channels()
        ->findSmsMarket('US', 'TEN_DLC');

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->senderValue)->toBe('Acme')
        ->and($result->status)->toBe('ACTIVE')
        ->and($result->note)->toBe('Ready to send')
        ->and($result->compliance)->toBe(['brand' => ['legal_name' => 'Acme']]);
});

it('channels()->addSmsMarket() adds an SMS market', function () {
    $result = sentApi([
        'country' => 'US', 'number_type' => 'TEN_DLC', 'sender_value' => null,
        'status' => 'PENDING', 'note' => 'Waiting for review', 'compliance' => null,
    ])
        ->channels()
        ->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC']);

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->status)->toBe('PENDING')
        ->and($result->note)->toBe('Waiting for review');
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

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->senderValue)->toBe('Acme')
        ->and($result->status)->toBe('ACTIVE')
        ->and($result->note)->toBe('Ready to send')
        ->and($result->compliance)->toBe(['brand' => ['legal_name' => 'Test Co']]);
});

it('channels()->addWhatsapp() adds a WhatsApp channel', function () {
    $result = sentApi([
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
        'owner_business_id' => 'biz-1', 'status' => 'PENDING',
    ])->channels()->addWhatsapp(['waba_id' => 'waba-1']);

    expect($result->wabaId)->toBe('waba-1')
        ->and($result->phoneNumberId)->toBe('phone-1')
        ->and($result->solutionId)->toBe('sol-1')
        ->and($result->ownerBusinessId)->toBe('biz-1')
        ->and($result->status)->toBe('PENDING');
});

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

it('channels()->whatsapp() builder adds a WhatsApp channel', function () {
    $result = sentApi([
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'solution_id' => 'sol-1',
        'owner_business_id' => 'biz-1', 'status' => 'PENDING',
    ])->channels()->whatsapp()->wabaId('waba-1')->phoneNumberId('phone-1')->save();

    expect($result->wabaId)->toBe('waba-1')
        ->and($result->phoneNumberId)->toBe('phone-1')
        ->and($result->solutionId)->toBe('sol-1')
        ->and($result->ownerBusinessId)->toBe('biz-1')
        ->and($result->status)->toBe('PENDING');
});

it('channels()->whatsapp() builder throws without a wabaId', function () {
    sentApi()->channels()->whatsapp()->save();
})->throws(InvalidArgumentException::class, 'A WABA id is required');

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

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->status)->toBe('PENDING');
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

    expect($result->country)->toBe('US')
        ->and($result->numberType)->toBe('TEN_DLC')
        ->and($result->senderValue)->toBe('Acme')
        ->and($result->compliance)->toBe(['brand' => ['legal_name' => 'Test Co']]);
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
