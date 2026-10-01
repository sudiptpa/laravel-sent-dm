<?php

declare(strict_types=1);

use SentDm\Client;
use Sujip\SentDm\Resources\Contacts;

it('Resource::profile() sends x-profile-id on a typed SDK call', function () {
    [$captured, $sent] = capturedSentHeaders(['contacts' => []]);
    $sent->contacts()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('no x-profile-id header is sent when profile() was never called', function () {
    [$captured, $sent] = capturedSentHeaders(['contacts' => []]);
    $sent->contacts()->get();

    expect($captured->headers)->not->toHaveKey('x-profile-id');
});

it('Resource::profile() sends x-profile-id through the raw() escape hatch', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->channels()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('Resource::profile() sends x-profile-id through messages resend', function () {
    [$captured, $sent] = capturedSentHeaders([
        'status' => 'QUEUED',
        'recipients' => [],
    ]);
    $sent->messages()->profile('child-profile-id')->resend('msg-1');

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('Resource::profile() sends x-profile-id through profiles complete', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->profiles()->profile('child-profile-id')->complete('prof-1', 'https://example.com/webhook');

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('Resource::profile() returns a new instance, leaving the original unscoped', function () {
    $contacts = new Contacts(client: new Client(apiKey: 'test'));
    $scoped = $contacts->profile('child-profile-id');

    expect($scoped)->not->toBe($contacts)
        ->and($scoped)->toBeInstanceOf(Contacts::class);
});
