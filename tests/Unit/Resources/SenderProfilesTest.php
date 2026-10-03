<?php

declare(strict_types=1);

use SentDm\Core\FileParam;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Responses\SenderProfileData;

it('senderProfiles()->get() lists sender profiles', function () {
    $result = sentApi([
        'sender_profiles' => [[
            'id' => 'sp-1',
            'organization_id' => 'org-1',
            'name' => 'Example Retail',
            'short_name' => 'Example',
            'description' => 'Retail sender profile',
            'api_key' => 'sk_live_abc',
            'billing' => ['inherit' => true],
            'channels' => ['sms' => ['country' => 'US']],
            'compliance' => ['brand' => []],
            'created_at' => '2026-09-11T00:00:00+00:00',
        ]],
        'pagination' => ['page' => 1, 'page_size' => 20, 'total_count' => 1],
    ])->senderProfiles()->get();

    $profile = $result->senderProfiles[0];
    expect($profile->id)->toBe('sp-1')
        ->and($profile->organizationId)->toBe('org-1')
        ->and($profile->name)->toBe('Example Retail')
        ->and($profile->shortName)->toBe('Example')
        ->and($profile->description)->toBe('Retail sender profile')
        ->and($profile->apiKey)->toBe('sk_live_abc')
        ->and($profile->billing)->toBe(['inherit' => true])
        ->and($profile->channels)->toBe(['sms' => ['country' => 'US']])
        ->and($profile->compliance)->toBe(['brand' => []])
        ->and($profile->createdAt)->toBe('2026-09-11T00:00:00+00:00')
        ->and($result->pagination)->toBe(['page' => 1, 'page_size' => 20, 'total_count' => 1]);
});

it('senderProfiles()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->senderProfiles();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('senderProfiles()->find() retrieves a sender profile', function () {
    $result = sentApi([
        'id' => 'sp-1',
        'organization_id' => 'org-1',
        'name' => 'Example Retail',
        'short_name' => 'Example',
        'description' => 'Retail sender profile',
        'api_key' => 'sk_live_abc',
        'billing' => ['inherit' => true],
        'notifications' => 'ORGANIZATION_AND_SENDER_PROFILE',
        'channels' => ['sms' => ['country' => 'US']],
        'compliance' => ['brand' => []],
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])->senderProfiles()->find('sp-1');

    expect($result)->toBeInstanceOf(SenderProfileData::class)
        ->and($result->id)->toBe('sp-1')
        ->and($result->organizationId)->toBe('org-1')
        ->and($result->name)->toBe('Example Retail')
        ->and($result->shortName)->toBe('Example')
        ->and($result->description)->toBe('Retail sender profile')
        ->and($result->apiKey)->toBe('sk_live_abc')
        ->and($result->billing)->toBe(['inherit' => true])
        ->and($result->notifications)->toBe('ORGANIZATION_AND_SENDER_PROFILE')
        ->and($result->channels)->toBe(['sms' => ['country' => 'US']])
        ->and($result->compliance)->toBe(['brand' => []])
        ->and($result->createdAt)->toBe('2026-09-11T00:00:00+00:00');
});

it('senderProfiles()->create() returns a SenderProfileBuilder', function () {
    expect(sentApi()->senderProfiles()->create())->toBeInstanceOf(SenderProfileBuilder::class);
});

it('senderProfiles()->create()->name()->shortName()->save() creates a sender profile', function () {
    $result = sentApi([
        'id' => 'sp-1',
        'organization_id' => 'org-1',
        'name' => 'Example Retail',
        'short_name' => 'Example',
        'description' => null,
        'api_key' => 'sk_live_new',
        'billing' => null,
        'channels' => null,
        'compliance' => null,
        'created_at' => '2026-09-12T00:00:00+00:00',
    ])
        ->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->save();

    expect($result->id)->toBe('sp-1')
        ->and($result->organizationId)->toBe('org-1')
        ->and($result->name)->toBe('Example Retail')
        ->and($result->shortName)->toBe('Example')
        ->and($result->apiKey)->toBe('sk_live_new')
        ->and($result->createdAt)->toBe('2026-09-12T00:00:00+00:00');
});

it('senderProfiles()->create()->attach()->save() sends a multipart request with a profile field and the file', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1', 'name' => 'Example Retail']);

    $sent->senderProfiles()->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data');
});

it('senderProfiles()->update()->attach()->save() throws, attach() is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();
})->throws(InvalidArgumentException::class, 'attach() is not supported on update()');

it('senderProfiles()->create()->save() throws without a name', function () {
    sentApi()->senderProfiles()->create()->shortName('Example')->save();
})->throws(InvalidArgumentException::class, 'A name is required');

it('senderProfiles()->create()->save() throws without a short name', function () {
    sentApi()->senderProfiles()->create()->name('Example Retail')->save();
})->throws(InvalidArgumentException::class, 'A short name is required');

it('senderProfiles()->create() builder exercises all setters', function () {
    $result = sentApi(['id' => 'sp-1'])
        ->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->description('Retail sender profile')
        ->billing(['inherit' => true])
        ->notifications('SENDER_PROFILE')
        ->channels(['sms' => ['country' => 'US', 'number_type' => 'TEN_DLC']])
        ->compliance(['brand' => []])
        ->sandbox(true)
        ->save();
    expect($result)->not->toBeNull();
});

it('senderProfiles()->create()->notifications()->save() sends notifications', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1', 'name' => 'Example Retail']);

    $sent->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->notifications('SENDER_PROFILE')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['notifications'])->toBe('SENDER_PROFILE');
});

it('senderProfiles()->update()->notifications()->save() sends notifications', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1', 'name' => 'Example Retail']);

    $sent->senderProfiles()
        ->update('sp-1')
        ->notifications('ORGANIZATION')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['notifications'])->toBe('ORGANIZATION');
});

it('senderProfiles()->create() chains are immutable', function () {
    $base = sentApi()->senderProfiles()->create();
    $chained = $base->name('Example Retail')->shortName('Example');
    expect($chained)->not->toBe($base);
});

it('senderProfiles()->update() returns a SenderProfileBuilder', function () {
    expect(sentApi()->senderProfiles()->update('sp-1'))->toBeInstanceOf(SenderProfileBuilder::class);
});

it('senderProfiles()->update()->name()->save() updates a sender profile without a short name', function () {
    $result = sentApi([
        'id' => 'sp-1',
        'organization_id' => 'org-1',
        'name' => 'New Name',
        'short_name' => 'Example',
        'description' => 'updated for real',
        'api_key' => 'sk_live_abc',
        'billing' => null,
        'channels' => null,
        'compliance' => null,
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])
        ->senderProfiles()
        ->update('sp-1')
        ->name('New Name')
        ->save();

    expect($result->id)->toBe('sp-1')
        ->and($result->organizationId)->toBe('org-1')
        ->and($result->name)->toBe('New Name')
        ->and($result->shortName)->toBe('Example')
        ->and($result->description)->toBe('updated for real')
        ->and($result->apiKey)->toBe('sk_live_abc');
});

it('senderProfiles()->update()->billing()->save() throws, billing is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->billing(['inherit' => true])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->update()->channels()->save() throws, channels is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->channels(['sms' => []])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->update()->compliance()->save() throws, compliance is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->compliance(['brand' => []])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->delete() deletes a sender profile', function () {
    sentApi([])->senderProfiles()->delete('sp-1');
    expect(true)->toBeTrue();
});
