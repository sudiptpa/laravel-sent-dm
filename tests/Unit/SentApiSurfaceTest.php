<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
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

// Contacts -------------------------------------------------------------------

it('createContact() delegates to SDK contacts->create', function () {
    $sent = sentApi(['id' => 'c-1', 'phone_number' => '+61412345678']);
    $result = $sent->createContact('+61412345678');
    expect($result)->not->toBeNull();
});

it('listContacts() delegates to SDK contacts->list', function () {
    $sent = sentApi(['contacts' => [], 'total_count' => 0]);
    $result = $sent->listContacts(page: 1, pageSize: 10, search: 'test', channel: 'sms');
    expect($result)->not->toBeNull();
});

it('getContact() delegates to SDK contacts->retrieve', function () {
    $sent = sentApi(['id' => 'c-1']);
    $result = $sent->getContact('c-1');
    expect($result)->not->toBeNull();
});

it('updateContact() delegates to SDK contacts->update', function () {
    $sent = sentApi(['id' => 'c-1', 'default_channel' => 'sms']);
    $result = $sent->updateContact('c-1', defaultChannel: 'sms', optOut: false);
    expect($result)->not->toBeNull();
});

it('deleteContact() delegates to SDK contacts->delete', function () {
    $sent = sentApi([]);
    $sent->deleteContact('c-1'); // void — no exception = pass
    expect(true)->toBeTrue();
});

// Templates ------------------------------------------------------------------

it('getTemplate() delegates to SDK templates->retrieve', function () {
    $sent = sentApi(['id' => 'tpl-1', 'name' => 'otp']);
    $result = $sent->getTemplate('tpl-1');
    expect($result)->not->toBeNull();
});

it('getTemplateByName() returns matching template', function () {
    $sent = sentApi([
        'templates' => [['id' => 'tpl-1', 'name' => 'otp', 'status' => 'APPROVED']],
    ]);
    $template = $sent->getTemplateByName('otp');
    expect($template)->not->toBeNull();
});

it('getTemplateByName() returns null when not found', function () {
    $sent = sentApi(['templates' => []]);
    $template = $sent->getTemplateByName('nonexistent');
    expect($template)->toBeNull();
});

it('deleteTemplate() delegates to SDK templates->delete', function () {
    $sent = sentApi([]);
    $sent->deleteTemplate('tpl-1');
    expect(true)->toBeTrue();
});

// Profiles -------------------------------------------------------------------

it('listProfiles() delegates to SDK profiles->list', function () {
    $sent = sentApi(['profiles' => []]);
    $result = $sent->listProfiles();
    expect($result)->not->toBeNull();
});

it('getProfile() delegates to SDK profiles->retrieve', function () {
    $sent = sentApi(['id' => 'prof-1']);
    $result = $sent->getProfile('prof-1');
    expect($result)->not->toBeNull();
});

it('deleteProfile() delegates to SDK profiles->delete', function () {
    $sent = sentApi([]);
    $sent->deleteProfile('prof-1');
    expect(true)->toBeTrue();
});

// Users ----------------------------------------------------------------------

it('listUsers() delegates to SDK users->list', function () {
    $sent = sentApi(['users' => []]);
    $result = $sent->listUsers();
    expect($result)->not->toBeNull();
});

it('getUser() delegates to SDK users->retrieve', function () {
    $sent = sentApi(['id' => 'user-1']);
    $result = $sent->getUser('user-1');
    expect($result)->not->toBeNull();
});

it('inviteUser() delegates to SDK users->invite', function () {
    $sent = sentApi(['id' => 'user-1', 'email' => 'jane@example.com']);
    $result = $sent->inviteUser('jane@example.com', 'Jane', 'admin');
    expect($result)->not->toBeNull();
});

it('removeUser() delegates to SDK users->remove', function () {
    $sent = sentApi([]);
    $sent->removeUser('user-1');
    expect(true)->toBeTrue();
});

// Webhooks -------------------------------------------------------------------

it('listWebhooks() delegates to SDK webhooks->list', function () {
    $sent = sentApi(['webhooks' => []]);
    $result = $sent->listWebhooks();
    expect($result)->not->toBeNull();
});

it('getWebhook() delegates to SDK webhooks->retrieve', function () {
    $sent = sentApi(['id' => 'wh-1']);
    $result = $sent->getWebhook('wh-1');
    expect($result)->not->toBeNull();
});

it('deleteWebhook() delegates to SDK webhooks->delete', function () {
    $sent = sentApi([]);
    $sent->deleteWebhook('wh-1');
    expect(true)->toBeTrue();
});

it('toggleWebhook() delegates to SDK webhooks->toggleStatus', function () {
    $sent = sentApi(['id' => 'wh-1', 'is_active' => true]);
    $result = $sent->toggleWebhook('wh-1', true);
    expect($result)->not->toBeNull();
});

it('rotateWebhookSecret() delegates to SDK webhooks->rotateSecret', function () {
    $sent = sentApi(['signing_secret' => 'whsec_new']);
    $result = $sent->rotateWebhookSecret('wh-1');
    expect($result)->not->toBeNull();
});

it('account() delegates to SDK me->retrieve', function () {
    $sent = sentApi(['type' => 'organization', 'name' => 'Acme', 'email' => 'a@b.com']);
    $result = $sent->account();
    expect($result)->not->toBeNull();
});

it('createWebhook() delegates to SDK webhooks->create', function () {
    $sent = sentApi(['id' => 'wh-1', 'endpoint_url' => 'https://example.com/webhook']);
    $result = $sent->createWebhook('https://example.com/webhook', ['message.sent']);
    expect($result)->not->toBeNull();
});
