<?php

declare(strict_types=1);

use Sujip\SentDm\Builders\UserInviteBuilder;
use Sujip\SentDm\Resources\Account;

it('users()->get() lists users', function () {
    $result = sentApi(['users' => [[
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'admin@acme.com',
        'name' => 'John Admin',
        'role' => 'admin',
        'status' => 'active',
        'invited_at' => '2026-03-11T14:57:36+00:00',
        'last_login_at' => '2026-09-11T12:57:36+00:00',
        'created_at' => '2026-03-11T14:57:36+00:00',
        'updated_at' => '2026-09-10T14:57:36+00:00',
    ]]])->users()->get();

    $user = $result->data->users[0];
    expect($user->id)->toBe('user-1')
        ->and($user->customerID)->toBe('cust-1')
        ->and($user->email)->toBe('admin@acme.com')
        ->and($user->name)->toBe('John Admin')
        ->and($user->role)->toBe('admin')
        ->and($user->status)->toBe('active')
        ->and($user->invitedAt)->not->toBeNull()
        ->and($user->lastLoginAt)->not->toBeNull();
});

it('users()->find() retrieves a user', function () {
    $result = sentApi([
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'admin@acme.com',
        'name' => 'John Admin',
        'role' => 'admin',
        'status' => 'active',
        'invited_at' => '2026-03-11T14:57:36+00:00',
        'last_login_at' => '2026-09-11T12:57:36+00:00',
        'created_at' => '2026-03-11T14:57:36+00:00',
        'updated_at' => '2026-09-10T14:57:36+00:00',
    ])->users()->find('user-1');

    expect($result->data->id)->toBe('user-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->email)->toBe('admin@acme.com')
        ->and($result->data->name)->toBe('John Admin')
        ->and($result->data->role)->toBe('admin')
        ->and($result->data->status)->toBe('active')
        ->and($result->data->invitedAt)->not->toBeNull()
        ->and($result->data->lastLoginAt)->not->toBeNull();
});

it('users()->invite() returns a UserInviteBuilder', function () {
    expect(sentApi()->users()->invite())->toBeInstanceOf(UserInviteBuilder::class);
});

it('users()->invite()->email()->name()->role()->save() invites a user', function () {
    $result = sentApi([
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'jane@example.com',
        'name' => 'Jane',
        'role' => 'admin',
        'status' => 'invited',
        'invited_at' => '2026-09-11T14:57:36+00:00',
        'last_login_at' => null,
        'created_at' => '2026-09-11T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
    ])
        ->users()
        ->invite()
        ->email('jane@example.com')
        ->name('Jane')
        ->role('admin')
        ->save();

    expect($result->data->id)->toBe('user-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->email)->toBe('jane@example.com')
        ->and($result->data->name)->toBe('Jane')
        ->and($result->data->role)->toBe('admin')
        ->and($result->data->status)->toBe('invited')
        ->and($result->data->lastLoginAt)->toBeNull();
});

it('users()->remove() removes a user', function () {
    sentApi([])->users()->remove('user-1');
    expect(true)->toBeTrue();
});

// Account + lookup -----------------------------------------------------------

it('account() delegates to SDK me->retrieve', function () {
    $result = sentApi(fullMeBody())->account();

    expect($result->data->type)->toBe('organization')
        ->and($result->data->id)->toBe('acct-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Acme')
        ->and($result->data->shortName)->toBe('ACM')
        ->and($result->data->email)->toBe('a@b.com')
        ->and($result->data->icon)->toBe('https://cdn.sent.dm/icons/acme.png')
        ->and($result->data->description)->toBe('Acme organization account')
        ->and($result->data->enableTemplateAutoCreationForSp)->toBeTrue()
        ->and($result->data->status)->toBe('approved')
        ->and($result->data->profiles)->toBe([])
        ->and($result->data->channels->sms->configured)->toBeTrue()
        ->and($result->data->channels->sms->phoneNumber)->toBe('+14155550100')
        ->and($result->data->channels->whatsapp->configured)->toBeTrue()
        ->and($result->data->channels->whatsapp->businessName)->toBe('Acme Corporation')
        ->and($result->data->channels->rcs->configured)->toBeFalse()
        ->and($result->data->settings->allowContactSharing)->toBeFalse()
        ->and($result->data->settings->allowTemplateSharing)->toBeFalse()
        ->and($result->data->settings->inheritContacts)->toBeFalse()
        ->and($result->data->settings->inheritTemplates)->toBeFalse()
        ->and($result->data->settings->inheritTcrBrand)->toBeTrue()
        ->and($result->data->settings->inheritTcrCampaign)->toBeFalse()
        ->and($result->data->settings->billingModel)->toBe('organization');
});

it('me() returns an Account resource', function () {
    expect(sentApi()->me())->toBeInstanceOf(Account::class);
});

it('me()->get() delegates to SDK me->retrieve', function () {
    $result = sentApi(fullMeBody())->me()->get();

    expect($result->data->type)->toBe('organization')
        ->and($result->data->name)->toBe('Acme')
        ->and($result->data->email)->toBe('a@b.com')
        ->and($result->data->enableTemplateAutoCreationForSp)->toBeTrue()
        ->and($result->data->channels->sms->configured)->toBeTrue()
        ->and($result->data->settings->billingModel)->toBe('organization');
});

it('me()->profile() sends x-profile-id, unlike account()', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->me()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('lookup() delegates to SDK numbers->lookup', function () {
    $result = sentApi([
        'phone_number' => '+61412345678',
        'is_valid' => true,
        'carrier_name' => 'Telstra',
        'line_type' => 'mobile',
        'country_code' => 'AU',
        'mobile_country_code' => '505',
        'mobile_network_code' => '01',
        'is_ported' => false,
        'is_voip' => false,
    ])->lookup('+61412345678');

    expect($result->data->phoneNumber)->toBe('+61412345678')
        ->and($result->data->isValid)->toBeTrue()
        ->and($result->data->carrierName)->toBe('Telstra')
        ->and($result->data->lineType)->toBe('mobile')
        ->and($result->data->countryCode)->toBe('AU')
        ->and($result->data->mobileCountryCode)->toBe('505')
        ->and($result->data->mobileNetworkCode)->toBe('01')
        ->and($result->data->isPorted)->toBeFalse()
        ->and($result->data->isVoip)->toBeFalse();
});

// Users: updateRole ---------------------------------------------------------

it('users()->updateRole() updates a user role', function () {
    $result = sentApi([
        'id' => 'user-1',
        'customer_id' => 'cust-1',
        'email' => 'user@example.com',
        'name' => 'User Name',
        'role' => 'billing',
        'status' => 'active',
        'invited_at' => '2026-08-11T14:57:36+00:00',
        'last_login_at' => '2026-09-11T09:57:36+00:00',
        'created_at' => '2026-08-11T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
    ])
        ->users()
        ->updateRole('user-1', 'admin');

    expect($result->data->id)->toBe('user-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->email)->toBe('user@example.com')
        ->and($result->data->name)->toBe('User Name')
        ->and($result->data->role)->toBe('billing')
        ->and($result->data->status)->toBe('active');
});
