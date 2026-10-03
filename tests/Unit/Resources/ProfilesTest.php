<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Builders\ProfileBuilder;
use Sujip\SentDm\Resources\Campaigns;
use Sujip\SentDm\Sent;

it('profiles()->get() lists profiles', function () {
    $result = sentApi([
        'profiles' => [[
            'id' => 'prof-1',
            'organization_id' => 'org-1',
            'name' => 'Marketing Team',
            'email' => 'team@acme.com',
            'icon' => 'https://example.com/marketing-icon.png',
            'description' => 'Marketing department sender profile',
            'short_name' => 'MKT',
            'status' => 'approved',
            'created_at' => '2026-06-11T14:57:37+00:00',
            'updated_at' => '2026-09-06T14:57:37+00:00',
            'allow_contact_sharing' => false,
            'allow_template_sharing' => false,
            'inherit_contacts' => false,
            'inherit_templates' => false,
            'inherit_tcr_brand' => false,
            'inherit_tcr_campaign' => false,
            'billing_model' => 'profile',
            'sending_phone_number_profile_id' => null,
            'sending_whatsapp_number_profile_id' => null,
            'sending_phone_number' => null,
            'whatsapp_phone_number' => null,
            'allow_number_change_during_onboarding' => null,
            'waba_id' => null,
            'billing_contact' => ['name' => 'Jane', 'email' => 'jane@acme.com', 'phone' => '+12125550123', 'address' => '1 Example St'],
            'brand' => [
                'id' => 'brand-1',
                'tcr_brand_id' => null,
                'status' => null,
                'identity_status' => null,
                'universal_ein' => null,
                'csp_id' => null,
                'submitted_to_tcr' => false,
                'submitted_at' => null,
                'is_inherited' => false,
                'created_at' => '2026-06-11T14:57:37+00:00',
                'updated_at' => null,
                'contact' => [
                    'name' => 'John Smith', 'business_name' => 'Acme Corp', 'role' => null,
                    'phone' => null, 'email' => 'john@acmecorp.com', 'phone_country_code' => null,
                ],
                'business' => [
                    'legal_name' => 'Acme Corporation LLC', 'tax_id' => null, 'tax_id_type' => null,
                    'entity_type' => null, 'street' => null, 'city' => null, 'state' => null,
                    'postal_code' => null, 'country' => 'US', 'url' => null, 'country_of_registration' => null,
                ],
                'compliance' => [
                    'vertical' => 'PROFESSIONAL', 'brand_relationship' => 'SMALL_ACCOUNT',
                    'is_tcr_application' => true, 'phone_number_prefix' => null,
                    'destination_countries' => [], 'notes' => null, 'primary_use_case' => null,
                ],
            ],
        ]],
        'pagination' => null,
    ])->profiles()->get();

    $profile = $result->data->profiles[0];
    expect($profile->id)->toBe('prof-1')
        ->and($profile->organizationID)->toBe('org-1')
        ->and($profile->name)->toBe('Marketing Team')
        ->and($profile->email)->toBe('team@acme.com')
        ->and($profile->icon)->toBe('https://example.com/marketing-icon.png')
        ->and($profile->description)->toBe('Marketing department sender profile')
        ->and($profile->shortName)->toBe('MKT')
        ->and($profile->status)->toBe('approved')
        ->and($profile->allowContactSharing)->toBeFalse()
        ->and($profile->allowTemplateSharing)->toBeFalse()
        ->and($profile->inheritContacts)->toBeFalse()
        ->and($profile->inheritTemplates)->toBeFalse()
        ->and($profile->inheritTcrBrand)->toBeFalse()
        ->and($profile->inheritTcrCampaign)->toBeFalse()
        ->and($profile->billingModel)->toBe('profile')
        ->and($profile->billingContact->name)->toBe('Jane')
        ->and($profile->billingContact->email)->toBe('jane@acme.com')
        ->and($profile->billingContact->phone)->toBe('+12125550123')
        ->and($profile->billingContact->address)->toBe('1 Example St')
        ->and($profile->brand->id)->toBe('brand-1')
        ->and($profile->brand->submittedToTcr)->toBeFalse()
        ->and($profile->brand->isInherited)->toBeFalse()
        ->and($profile->brand->contact->name)->toBe('John Smith')
        ->and($profile->brand->contact->businessName)->toBe('Acme Corp')
        ->and($profile->brand->contact->email)->toBe('john@acmecorp.com')
        ->and($profile->brand->business->legalName)->toBe('Acme Corporation LLC')
        ->and($profile->brand->business->country)->toBe('US')
        ->and($profile->brand->compliance->vertical)->toBe('PROFESSIONAL')
        ->and($profile->brand->compliance->brandRelationship)->toBe('SMALL_ACCOUNT')
        ->and($profile->brand->compliance->isTcrApplication)->toBeTrue()
        ->and($profile->brand->compliance->destinationCountries)->toBe([]);
});

it('profiles()->find() retrieves a profile', function () {
    $result = sentApi([
        'id' => 'prof-1',
        'organization_id' => 'org-1',
        'name' => 'Marketing Team',
        'email' => 'team@acme.com',
        'icon' => 'https://example.com/icon.png',
        'description' => 'Marketing department sender profile',
        'short_name' => 'MKT',
        'status' => 'approved',
        'created_at' => '2026-06-11T14:57:37+00:00',
        'updated_at' => '2026-09-06T14:57:37+00:00',
        'allow_contact_sharing' => false,
        'allow_template_sharing' => false,
        'inherit_contacts' => false,
        'inherit_templates' => false,
        'inherit_tcr_brand' => false,
        'inherit_tcr_campaign' => false,
        'billing_model' => 'profile',
        'sending_phone_number' => '+12125550123',
        'sending_phone_number_profile_id' => null,
        'sending_whatsapp_number_profile_id' => null,
        'whatsapp_phone_number' => null,
        'allow_number_change_during_onboarding' => true,
        'waba_id' => 'waba-1',
        'billing_contact' => null,
        'brand' => null,
    ])->profiles()->find('prof-1');

    expect($result->data->id)->toBe('prof-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Marketing Team')
        ->and($result->data->email)->toBe('team@acme.com')
        ->and($result->data->icon)->toBe('https://example.com/icon.png')
        ->and($result->data->description)->toBe('Marketing department sender profile')
        ->and($result->data->shortName)->toBe('MKT')
        ->and($result->data->status)->toBe('approved')
        ->and($result->data->allowContactSharing)->toBeFalse()
        ->and($result->data->allowTemplateSharing)->toBeFalse()
        ->and($result->data->inheritContacts)->toBeFalse()
        ->and($result->data->inheritTemplates)->toBeFalse()
        ->and($result->data->inheritTcrBrand)->toBeFalse()
        ->and($result->data->inheritTcrCampaign)->toBeFalse()
        ->and($result->data->billingModel)->toBe('profile')
        ->and($result->data->sendingPhoneNumber)->toBe('+12125550123')
        ->and($result->data->allowNumberChangeDuringOnboarding)->toBeTrue()
        ->and($result->data->wabaID)->toBe('waba-1');
});

it('profiles()->delete() deletes a profile', function () {
    sentApi([])->profiles()->delete('prof-1');
    expect(true)->toBeTrue();
});

it('profiles()->create() returns a ProfileBuilder', function () {
    expect(sentApi()->profiles()->create())->toBeInstanceOf(ProfileBuilder::class);
});

it('profiles()->create()->name()->save() creates a profile', function () {
    $result = sentApi([
        'id' => 'prof-1',
        'organization_id' => 'org-1',
        'name' => 'Sales',
        'email' => null,
        'icon' => null,
        'description' => null,
        'short_name' => null,
        'status' => 'pending',
        'created_at' => '2026-09-11T00:00:00+00:00',
        'updated_at' => null,
        'allow_contact_sharing' => false,
        'allow_template_sharing' => false,
        'inherit_contacts' => false,
        'inherit_templates' => false,
        'inherit_tcr_brand' => false,
        'inherit_tcr_campaign' => false,
        'billing_model' => 'organization',
    ])->profiles()->create()->name('Sales')->save();

    expect($result->data->id)->toBe('prof-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Sales')
        ->and($result->data->status)->toBe('pending')
        ->and($result->data->allowContactSharing)->toBeFalse()
        ->and($result->data->allowTemplateSharing)->toBeFalse()
        ->and($result->data->inheritContacts)->toBeFalse()
        ->and($result->data->inheritTemplates)->toBeFalse()
        ->and($result->data->inheritTcrBrand)->toBeFalse()
        ->and($result->data->inheritTcrCampaign)->toBeFalse()
        ->and($result->data->billingModel)->toBe('organization');
});

it('profiles()->create() chains are immutable', function () {
    $base = sentApi()->profiles()->create();
    $chained = $base->name('Sales')->description('Sales profile');
    expect($chained)->not->toBe($base);
});

it('profiles()->create() builder exercises all create setters', function () {
    $base = sentApi(['id' => 'prof-1'])->profiles()->create();
    $result = $base
        ->name('Test')
        ->description('desc')
        ->shortName('TST')
        ->icon('https://example.com/icon.png')
        ->billingModel('organization')
        ->inheritContacts(true)
        ->inheritTemplates(true)
        ->inheritTcrBrand(true)
        ->inheritTcrCampaign(true)
        ->allowContactSharing(true)
        ->allowTemplateSharing(true)
        ->billingContact([])
        ->brand([])
        ->paymentDetails([])
        ->whatsappBusinessAccount([])
        ->save();
    expect($result)->not->toBeNull();
});

it('profiles()->update() returns a ProfileBuilder', function () {
    expect(sentApi()->profiles()->update('prof-1'))->toBeInstanceOf(ProfileBuilder::class);
});

it('profiles()->update()->name()->save() updates a profile', function () {
    $result = sentApi([
        'id' => 'prof-1',
        'organization_id' => 'org-1',
        'name' => 'Support',
        'status' => 'approved',
        'sending_phone_number' => '+12125550123',
        'sending_phone_number_profile_id' => 'prof-2',
        'sending_whatsapp_number_profile_id' => 'prof-3',
        'whatsapp_phone_number' => '+12125550123',
        'allow_number_change_during_onboarding' => true,
        'updated_at' => '2026-09-12T00:00:00+00:00',
    ])->profiles()->update('prof-1')->name('Support')->save();

    expect($result->data->id)->toBe('prof-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Support')
        ->and($result->data->status)->toBe('approved')
        ->and($result->data->sendingPhoneNumber)->toBe('+12125550123')
        ->and($result->data->sendingPhoneNumberProfileID)->toBe('prof-2')
        ->and($result->data->sendingWhatsappNumberProfileID)->toBe('prof-3')
        ->and($result->data->whatsappPhoneNumber)->toBe('+12125550123')
        ->and($result->data->allowNumberChangeDuringOnboarding)->toBeTrue();
});

it('profiles()->update() chains are immutable', function () {
    $base = sentApi()->profiles()->update('prof-1');
    $chained = $base->name('Support')->shortName('SUP');
    expect($chained)->not->toBe($base);
});

it('profiles()->update() builder exercises all update-only setters', function () {
    $base = sentApi(['id' => 'prof-1'])->profiles()->update('prof-1');
    $result = $base
        ->allowNumberChangeDuringOnboarding(true)
        ->sendingPhoneNumber('+61412345678')
        ->sendingPhoneNumberProfileId('prof-2')
        ->sendingWhatsappNumberProfileId('prof-3')
        ->whatsappPhoneNumber('+61412345678')
        ->save();
    expect($result)->not->toBeNull();
});

it('profiles()->complete() triggers profile completion', function () {
    sentApi([])->profiles()->complete('prof-1', 'https://example.com/webhook');
    expect(true)->toBeTrue();
});

it('profiles()->complete() handles the 204 No Content the live API sends while onboarding finishes in the background', function () {
    $transporter = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(204);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    $result = $sent->profiles()->complete('prof-1', 'https://example.com/webhook');

    expect($result)->toBeNull();
});

it('profiles()->campaigns() returns a Campaigns resource', function () {
    expect(sentApi()->profiles()->campaigns('prof-1'))->toBeInstanceOf(Campaigns::class);
});

it('profiles()->campaigns()->get() lists campaigns', function () {
    $result = sentApi([])->profiles()->campaigns('prof-1')->get();
    expect($result->data)->toBe([]);
});

it('profiles()->campaigns()->create() creates a campaign', function () {
    $result = sentApi([
        'id' => 'camp-1',
        'customerId' => 'cust-1',
        'type' => 'App',
        'name' => 'Customer Notifications',
        'description' => 'Appointment reminders',
        'brandId' => 'brand-1',
        'status' => null,
        'tcrCampaignId' => null,
        'submittedToTCR' => false,
        'submittedAt' => null,
        'cost' => null,
        'billedDate' => null,
        'messageFlow' => 'User opts in on the website',
        'volume' => '1500',
        'privacyPolicyLink' => 'https://example.com/privacy',
        'termsAndConditionsLink' => 'https://example.com/terms',
        'optinMessage' => 'You are subscribed',
        'optoutMessage' => 'You are unsubscribed',
        'helpMessage' => 'Reply HELP for help',
        'optinKeywords' => 'START',
        'optoutKeywords' => 'STOP',
        'helpKeywords' => 'HELP',
        'dcaElectionsComplete' => false,
        'dcaElectionsCompletedAt' => null,
        'tcrSyncError' => null,
        'hasSubmissionTransaction' => false,
        'useCases' => [[
            'id' => 'uc-1', 'campaignId' => 'camp-1', 'customerId' => 'cust-1',
            'messagingUseCaseUs' => 'ACCOUNT_NOTIFICATION',
            'sampleMessages' => ['Your appointment is confirmed.'],
            'createdAt' => '2026-09-11T14:57:37+00:00', 'updatedAt' => null,
        ]],
    ])
        ->profiles()
        ->campaigns('prof-1')
        ->create(['description' => 'OTP', 'name' => 'OTP', 'type' => 'KYC', 'useCases' => []]);

    expect($result->data->id)->toBe('camp-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->type)->toBe('App')
        ->and($result->data->name)->toBe('Customer Notifications')
        ->and($result->data->description)->toBe('Appointment reminders')
        ->and($result->data->brandID)->toBe('brand-1')
        ->and($result->data->submittedToTcr)->toBeFalse()
        ->and($result->data->messageFlow)->toBe('User opts in on the website')
        ->and($result->data->volume)->toBe('1500')
        ->and($result->data->privacyPolicyLink)->toBe('https://example.com/privacy')
        ->and($result->data->termsAndConditionsLink)->toBe('https://example.com/terms')
        ->and($result->data->optinMessage)->toBe('You are subscribed')
        ->and($result->data->optoutMessage)->toBe('You are unsubscribed')
        ->and($result->data->helpMessage)->toBe('Reply HELP for help')
        ->and($result->data->optinKeywords)->toBe('START')
        ->and($result->data->optoutKeywords)->toBe('STOP')
        ->and($result->data->helpKeywords)->toBe('HELP')
        ->and($result->data->dcaElectionsComplete)->toBeFalse()
        ->and($result->data->hasSubmissionTransaction)->toBeFalse()
        ->and($result->data->useCases[0]->id)->toBe('uc-1')
        ->and($result->data->useCases[0]->campaignID)->toBe('camp-1')
        ->and($result->data->useCases[0]->messagingUseCaseUs)->toBe('ACCOUNT_NOTIFICATION')
        ->and($result->data->useCases[0]->sampleMessages)->toBe(['Your appointment is confirmed.']);
});

it('profiles()->campaigns()->update() updates a campaign', function () {
    $result = sentApi(['id' => 'camp-1', 'name' => 'Customer Notifications Updated'])
        ->profiles()
        ->campaigns('prof-1')
        ->update('camp-1', ['description' => 'OTP v2', 'name' => 'OTP', 'type' => 'KYC', 'useCases' => []]);
    expect($result->data->id)->toBe('camp-1')
        ->and($result->data->name)->toBe('Customer Notifications Updated');
});

it('profiles()->campaigns()->delete() deletes a campaign', function () {
    sentApi([])->profiles()->campaigns('prof-1')->delete('camp-1');
    expect(true)->toBeTrue();
});
