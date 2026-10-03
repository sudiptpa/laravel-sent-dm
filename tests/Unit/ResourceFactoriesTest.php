<?php

declare(strict_types=1);

use Sujip\SentDm\Resources\Calls;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Resources\Compliance;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Conversations;
use Sujip\SentDm\Resources\Messages;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\SenderProfiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;

// Resource factories ---------------------------------------------------------

it('contacts() returns a Contacts resource', function () {
    expect(sentApi()->contacts())->toBeInstanceOf(Contacts::class);
});

it('conversations() returns a Conversations resource', function () {
    expect(sentApi()->conversations())->toBeInstanceOf(Conversations::class);
});

it('messages() returns a Messages resource', function () {
    expect(sentApi()->messages())->toBeInstanceOf(Messages::class);
});

it('calls() returns a Calls resource', function () {
    expect(sentApi()->calls())->toBeInstanceOf(Calls::class);
});

it('templates() returns a Templates resource', function () {
    expect(sentApi()->templates())->toBeInstanceOf(Templates::class);
});

it('webhooks() returns a Webhooks resource', function () {
    expect(sentApi()->webhooks())->toBeInstanceOf(Webhooks::class);
});

it('profiles() returns a Profiles resource', function () {
    expect(sentApi()->profiles())->toBeInstanceOf(Profiles::class);
});

it('users() returns a Users resource', function () {
    expect(sentApi()->users())->toBeInstanceOf(Users::class);
});

it('senderProfiles() returns a SenderProfiles resource', function () {
    expect(sentApi()->senderProfiles())->toBeInstanceOf(SenderProfiles::class);
});

it('channels() returns a Channels resource', function () {
    expect(sentApi()->channels())->toBeInstanceOf(Channels::class);
});

it('compliance() returns a Compliance resource', function () {
    expect(sentApi()->compliance())->toBeInstanceOf(Compliance::class);
});
