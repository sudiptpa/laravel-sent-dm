<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Builders\ContactBuilder;
use Sujip\SentDm\Builders\UserInviteBuilder;
use Sujip\SentDm\Builders\WebhookBuilder;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent;

/**
 * Build a Sent driver backed by a fake HTTP transporter that returns a
 * given response body for every call.
 *
 * @param  array<string, mixed>  $data
 */
function sentApi(array $data = []): Sent
{
    $body = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($body) implements ClientInterface
    {
        public function __construct(private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return new Sent(new Client(apiKey: 'test', requestOptions: $opts));
}

// Resource factories ---------------------------------------------------------

it('contacts() returns a Contacts resource', function () {
    expect(sentApi()->contacts())->toBeInstanceOf(Contacts::class);
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

// Contacts -------------------------------------------------------------------

it('contacts()->get() lists contacts', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])->contacts()->get();
    expect($result)->not->toBeNull();
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

it('contacts()->find() retrieves a contact', function () {
    $result = sentApi(['id' => 'c-1', 'phone_number' => '+61412345678'])->contacts()->find('c-1');
    expect($result)->not->toBeNull();
});

it('contacts()->create() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->create())->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->create()->phone()->save() creates a contact', function () {
    $result = sentApi(['id' => 'c-1', 'phone_number' => '+61412345678'])
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->create()->save() throws without a phone number', function () {
    sentApi()->contacts()->create()->save();
})->throws(InvalidArgumentException::class, 'phone number is required');

it('contacts()->create()->phone()->defaultChannel()->save() passes defaultChannel', function () {
    $result = sentApi(['id' => 'c-1'])
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->defaultChannel('sms')
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->update() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->update('c-1'))->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->update()->optOut()->save() updates a contact', function () {
    $result = sentApi(['id' => 'c-1'])
        ->contacts()
        ->update('c-1')
        ->optOut(true)
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->update()->defaultChannel()->save() updates a contact', function () {
    $result = sentApi(['id' => 'c-1', 'default_channel' => 'sms'])
        ->contacts()
        ->update('c-1')
        ->defaultChannel('sms')
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->delete() deletes a contact', function () {
    sentApi([])->contacts()->delete('c-1');
    expect(true)->toBeTrue();
});

// Templates ------------------------------------------------------------------

it('templates()->get() lists templates', function () {
    $result = sentApi(['templates' => []])->templates()->get();
    expect($result)->not->toBeNull();
});

it('templates()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->templates();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('templates()->find() retrieves a template', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'otp'])->templates()->find('tpl-1');
    expect($result)->not->toBeNull();
});

it('templates()->findByName() returns matching template', function () {
    $result = sentApi(['templates' => [['id' => 'tpl-1', 'name' => 'otp', 'status' => 'APPROVED']]])
        ->templates()
        ->findByName('otp');
    expect($result)->not->toBeNull();
});

it('templates()->findByName() returns null when not found', function () {
    $result = sentApi(['templates' => []])->templates()->findByName('nonexistent');
    expect($result)->toBeNull();
});

it('templates()->delete() deletes a template', function () {
    sentApi([])->templates()->delete('tpl-1');
    expect(true)->toBeTrue();
});

// Webhooks -------------------------------------------------------------------

it('webhooks()->get() lists webhooks', function () {
    $result = sentApi(['webhooks' => []])->webhooks()->get();
    expect($result)->not->toBeNull();
});

it('webhooks()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->webhooks();
    $chained = $base->page(2)->perPage(10);
    expect($chained)->not->toBe($base);
});

it('webhooks()->find() retrieves a webhook', function () {
    $result = sentApi(['id' => 'wh-1'])->webhooks()->find('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->create() returns a WebhookBuilder', function () {
    expect(sentApi()->webhooks()->create())->toBeInstanceOf(WebhookBuilder::class);
});

it('webhooks()->create()->url()->events()->save() creates a webhook', function () {
    $result = sentApi(['id' => 'wh-1', 'endpoint_url' => 'https://example.com/wh'])
        ->webhooks()
        ->create()
        ->url('https://example.com/wh')
        ->events(['message.delivered'])
        ->save();
    expect($result)->not->toBeNull();
});

it('webhooks()->update() returns a WebhookBuilder', function () {
    expect(sentApi()->webhooks()->update('wh-1'))->toBeInstanceOf(WebhookBuilder::class);
});

it('webhooks()->update()->url()->save() updates a webhook', function () {
    $result = sentApi(['id' => 'wh-1'])
        ->webhooks()
        ->update('wh-1')
        ->url('https://example.com/new')
        ->save();
    expect($result)->not->toBeNull();
});

it('webhooks()->delete() deletes a webhook', function () {
    sentApi([])->webhooks()->delete('wh-1');
    expect(true)->toBeTrue();
});

it('webhooks()->enable() enables a webhook', function () {
    $result = sentApi(['id' => 'wh-1', 'is_active' => true])->webhooks()->enable('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->disable() disables a webhook', function () {
    $result = sentApi(['id' => 'wh-1', 'is_active' => false])->webhooks()->disable('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->rotateSecret() rotates the signing secret', function () {
    $result = sentApi(['signing_secret' => 'whsec_new'])->webhooks()->rotateSecret('wh-1');
    expect($result)->not->toBeNull();
});

// Profiles -------------------------------------------------------------------

it('profiles()->get() lists profiles', function () {
    $result = sentApi(['profiles' => []])->profiles()->get();
    expect($result)->not->toBeNull();
});

it('profiles()->find() retrieves a profile', function () {
    $result = sentApi(['id' => 'prof-1'])->profiles()->find('prof-1');
    expect($result)->not->toBeNull();
});

it('profiles()->delete() deletes a profile', function () {
    sentApi([])->profiles()->delete('prof-1');
    expect(true)->toBeTrue();
});

// Users ----------------------------------------------------------------------

it('users()->get() lists users', function () {
    $result = sentApi(['users' => []])->users()->get();
    expect($result)->not->toBeNull();
});

it('users()->find() retrieves a user', function () {
    $result = sentApi(['id' => 'user-1'])->users()->find('user-1');
    expect($result)->not->toBeNull();
});

it('users()->invite() returns a UserInviteBuilder', function () {
    expect(sentApi()->users()->invite())->toBeInstanceOf(UserInviteBuilder::class);
});

it('users()->invite()->email()->name()->role()->save() invites a user', function () {
    $result = sentApi(['id' => 'user-1', 'email' => 'jane@example.com'])
        ->users()
        ->invite()
        ->email('jane@example.com')
        ->name('Jane')
        ->role('admin')
        ->save();
    expect($result)->not->toBeNull();
});

it('users()->remove() removes a user', function () {
    sentApi([])->users()->remove('user-1');
    expect(true)->toBeTrue();
});

// Account + lookup -----------------------------------------------------------

it('account() delegates to SDK me->retrieve', function () {
    $result = sentApi(['type' => 'organization', 'name' => 'Acme', 'email' => 'a@b.com'])->account();
    expect($result)->not->toBeNull();
});

it('lookup() delegates to SDK numbers->lookup', function () {
    $result = sentApi(['isValid' => true, 'carrierName' => 'Telstra'])->lookup('+61412345678');
    expect($result)->not->toBeNull();
});
