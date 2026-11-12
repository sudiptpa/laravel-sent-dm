<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\Core\FileParam;
use SentDm\RequestOptions;
use SentDm\SenderProfiles\APIResponseOfSenderProfile;
use SentDm\SenderProfilesPage;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Sent;

it('senderProfiles()->get() lists sender profiles', function () {
    $result = sentApi([
        'sender_profiles' => [[
            'id' => 'sp-1',
            'organization_id' => 'org-1',
            'name' => 'Example Retail',
            'short_name' => 'Example',
            'description' => 'Retail sender profile',
            'billing' => ['inherit' => true],
            'notifications' => 'ORGANIZATION',
            'channels' => ['sms' => [['country' => 'US', 'number_type' => 'TEN_DLC', 'status' => 'ACTIVE']]],
            'compliance' => ['brand' => ['legal_name' => 'Acme']],
            'created_at' => '2026-09-11T00:00:00+00:00',
        ]],
        'pagination' => ['page' => 1, 'page_size' => 20, 'total_count' => 1, 'has_more' => false],
    ])->senderProfiles()->get();

    $profile = $result->getItems()[0];

    expect($result)->toBeInstanceOf(SenderProfilesPage::class)
        ->and($result->getItems())->toHaveCount(1)
        ->and($profile->id)->toBe('sp-1')
        ->and($profile->organizationID)->toBe('org-1')
        ->and($profile->name)->toBe('Example Retail')
        ->and($profile->shortName)->toBe('Example')
        ->and($profile->description)->toBe('Retail sender profile')
        ->and($profile->billing?->inherit)->toBeTrue()
        ->and($profile->notifications)->toBe('ORGANIZATION')
        ->and($profile->channels->sms[0]->country)->toBe('US')
        ->and($profile->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and(isset($profile->apiKey))->toBeFalse();
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
        'billing' => ['inherit' => true],
        'notifications' => 'ORGANIZATION',
        'channels' => ['sms' => [['country' => 'US', 'number_type' => 'TEN_DLC', 'status' => 'ACTIVE']]],
        'compliance' => ['brand' => ['legal_name' => 'Acme']],
        'created_at' => '2026-09-11T00:00:00+00:00',
    ])->senderProfiles()->find('sp-1');

    expect($result)->toBeInstanceOf(APIResponseOfSenderProfile::class)
        ->and($result->data->id)->toBe('sp-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Example Retail')
        ->and($result->data->shortName)->toBe('Example')
        ->and($result->data->description)->toBe('Retail sender profile')
        ->and($result->data->billing?->inherit)->toBeTrue()
        ->and($result->data->notifications)->toBe('ORGANIZATION')
        ->and($result->data->compliance->brand)->toBe(['legal_name' => 'Acme'])
        ->and($result->data->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and(isset($result->data->apiKey))->toBeFalse();
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

    expect($result->data->id)->toBe('sp-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('Example Retail')
        ->and($result->data->shortName)->toBe('Example')
        ->and($result->data->apiKey)->toBe('sk_live_new');
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
    expect($result->data->id)->toBe('sp-1');
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

    expect($result->data->id)->toBe('sp-1')
        ->and($result->data->organizationID)->toBe('org-1')
        ->and($result->data->name)->toBe('New Name')
        ->and($result->data->shortName)->toBe('Example')
        ->and($result->data->description)->toBe('updated for real')
        ->and($result->data->apiKey)->toBe('sk_live_abc');
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
    [$captured, $sent] = capturedSentHeaders([]);

    $sent->senderProfiles()->delete('sp-1');

    expect($captured->method)->toBe('DELETE')
        ->and(parse_url((string) $captured->uri, PHP_URL_PATH))->toBe('/v3/sender-profiles/sp-1')
        ->and($captured->body)->toBeIn([null, '']);
});

it('senderProfiles()->get() sends the page and page size as the query', function () {
    [$captured, $sent] = capturedSentHeaders(['sender_profiles' => [], 'pagination' => ['page' => 2]]);

    $sent->senderProfiles()->page(2)->perPage(25)->get();
    parse_str((string) parse_url((string) $captured->uri, PHP_URL_QUERY), $query);

    expect($captured->method)->toBe('GET')
        ->and(parse_url((string) $captured->uri, PHP_URL_PATH))->toBe('/v3/sender-profiles')
        ->and($query)->toBe(['page' => '2', 'page_size' => '25']);
});

it('senderProfiles()->find() reads the profile by path and encodes the id', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1']);

    $sent->senderProfiles()->find('sp-1');
    expect($captured->method)->toBe('GET')
        ->and(parse_url((string) $captured->uri, PHP_URL_PATH))->toBe('/v3/sender-profiles/sp-1');

    $sent->senderProfiles()->find('a/b c');
    expect($captured->uri)->toContain('/v3/sender-profiles/a%2Fb%20c');
});

it('senderProfiles()->profile() scopes get, find and delete to a child profile', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1', 'sender_profiles' => []]);
    $scoped = $sent->senderProfiles()->profile('prof-1');

    $scoped->get();
    expect($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);

    $captured->headers = null;
    $scoped->find('sp-1');
    expect($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);

    $captured->headers = null;
    $scoped->delete('sp-1');
    expect($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);
});

it('senderProfiles() sends no profile header when no profile is set', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1']);

    $sent->senderProfiles()->find('sp-1');

    expect($captured->headers['x-profile-id'] ?? null)->toBeNull();
});

it('senderProfiles()->create()->save() sends snake_case wire fields and keeps nested keys', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1']);

    $sent->senderProfiles()->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->channels(['sms' => ['country' => 'US', 'number_type' => 'TEN_DLC']])
        ->idempotencyKey('idem-1')
        ->save();

    expect($captured->uri)->toContain('/v3/sender-profiles')
        ->and(json_decode((string) $captured->body, true))->toBe([
            'channels' => ['sms' => ['country' => 'US', 'number_type' => 'TEN_DLC']],
            'name' => 'Example Retail',
            'short_name' => 'Example',
        ])
        ->and($captured->headers['Idempotency-Key'] ?? [])->toBe(['idem-1']);
});

it('senderProfiles()->create()->attach()->save() sends nested keys under their wire names', function (array $channels) {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1']);

    $sent->senderProfiles()->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->channels($channels)
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->sandbox(true)
        ->save();

    preg_match('/name="profile"\r\nContent-Type: text\/plain\r\n\r\n(\{.*?\})\r\n/s', (string) $captured->body, $match);

    expect(json_decode($match[1], true))->toBe([
        'channels' => ['sms' => ['country' => 'XK', 'number_type' => 'ALPHANUMERIC', 'sender_value' => 'EXAMPLE']],
        'name' => 'Example Retail',
        'sandbox' => true,
        'short_name' => 'Example',
    ]);
})->with([
    'camelCase keys' => [['sms' => ['country' => 'XK', 'numberType' => 'ALPHANUMERIC', 'senderValue' => 'EXAMPLE']]],
    'snake_case keys' => [['sms' => ['country' => 'XK', 'number_type' => 'ALPHANUMERIC', 'sender_value' => 'EXAMPLE']]],
]);

it('senderProfiles()->profile()->get() keeps the profile scope on the next page', function () {
    $requests = new class
    {
        /** @var list<array{uri: string, scope: string}> */
        public array $log = [];
    };

    $transporter = new class($requests) implements ClientInterface
    {
        public function __construct(private object $requests) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->requests->log[] = ['uri' => (string) $r->getUri(), 'scope' => $r->getHeaderLine('x-profile-id')];
            $page = count($this->requests->log);

            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'success' => true,
                'data' => [
                    'sender_profiles' => [['id' => "sp-$page", 'name' => "Profile $page"]],
                    'pagination' => ['page' => $page, 'page_size' => 1, 'total_count' => 2, 'total_pages' => 2, 'has_more' => $page < 2],
                ],
                'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
            ]) ?: '{}');
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    $first = $sent->senderProfiles()->profile('prof-1')->perPage(1)->get();
    $second = $first->getNextPage();

    expect($first->hasNextPage())->toBeTrue()
        ->and($second->getItems()[0]->id)->toBe('sp-2')
        ->and($second->hasNextPage())->toBeFalse()
        ->and($requests->log)->toHaveCount(2)
        ->and($requests->log[0]['scope'])->toBe('prof-1')
        ->and($requests->log[1]['scope'])->toBe('prof-1')
        ->and($requests->log[1]['uri'])->toContain('page=2');
});
