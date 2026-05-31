<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

/**
 * Build a fake Sent driver backed by a PSR-18 transporter and register it
 * on the real SentManager via extend(). This means all proxy method calls
 * go through the real SentManager class methods — coverage is recorded.
 *
 * @param  array<string, mixed>  $data
 */
function extendManagerWithFake(array $data = []): SentManager
{
    $body = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 't', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
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

    $driver = new Sent(client: new Client(apiKey: 'test', requestOptions: $opts));

    /** @var SentManager $manager */
    $manager = app(SentManager::class);
    $manager->extend('default', fn () => $driver);
    $manager->forgetDrivers();

    return $manager;
}

// Proxy methods — all go through the real SentManager class (coverage recorded) ----------

it('SentManager::to() proxies to default driver', function () {
    $m = extendManagerWithFake();
    expect($m->to('+61412345678'))->not->toBeNull();
});

it('SentManager::bulk() proxies to default driver', function () {
    $m = extendManagerWithFake();
    expect($m->bulk(['+61412345678']))->not->toBeNull();
});

it('SentManager::account() proxies to default driver', function () {
    $m = extendManagerWithFake(['type' => 'organization', 'name' => 'Acme', 'email' => 'a@b.com']);
    expect($m->account())->not->toBeNull();
});

it('SentManager::listTemplates() proxies to default driver', function () {
    $m = extendManagerWithFake(['templates' => []]);
    expect($m->listTemplates())->not->toBeNull();
});

it('SentManager::lookup() proxies to default driver', function () {
    $m = extendManagerWithFake(['isValid' => true, 'carrierName' => 'Telstra']);
    expect($m->lookup('+61412345678'))->not->toBeNull();
});

it('SentManager::createWebhook() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'wh-1']);
    expect($m->createWebhook('https://example.com/wh', ['message.sent']))->not->toBeNull();
});

it('SentManager::createContact() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'c-1', 'phone_number' => '+61412345678']);
    expect($m->createContact('+61412345678'))->not->toBeNull();
});

it('SentManager::listContacts() proxies to default driver', function () {
    $m = extendManagerWithFake(['contacts' => [], 'total_count' => 0]);
    expect($m->listContacts())->not->toBeNull();
});

it('SentManager::getContact() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'c-1']);
    expect($m->getContact('c-1'))->not->toBeNull();
});

it('SentManager::updateContact() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'c-1']);
    expect($m->updateContact('c-1', 'sms'))->not->toBeNull();
});

it('SentManager::deleteContact() proxies to default driver', function () {
    extendManagerWithFake()->deleteContact('c-1');
    expect(true)->toBeTrue();
});

it('SentManager::getTemplate() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'tpl-1', 'name' => 'otp']);
    expect($m->getTemplate('tpl-1'))->not->toBeNull();
});

it('SentManager::getTemplateByName() proxies to default driver', function () {
    $m = extendManagerWithFake(['templates' => []]);
    expect($m->getTemplateByName('otp'))->toBeNull();
});

it('SentManager::deleteTemplate() proxies to default driver', function () {
    extendManagerWithFake()->deleteTemplate('tpl-1');
    expect(true)->toBeTrue();
});

it('SentManager::listProfiles() proxies to default driver', function () {
    $m = extendManagerWithFake(['profiles' => []]);
    expect($m->listProfiles())->not->toBeNull();
});

it('SentManager::getProfile() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'prof-1']);
    expect($m->getProfile('prof-1'))->not->toBeNull();
});

it('SentManager::deleteProfile() proxies to default driver', function () {
    extendManagerWithFake()->deleteProfile('prof-1');
    expect(true)->toBeTrue();
});

it('SentManager::listUsers() proxies to default driver', function () {
    $m = extendManagerWithFake(['users' => []]);
    expect($m->listUsers())->not->toBeNull();
});

it('SentManager::getUser() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'u-1']);
    expect($m->getUser('u-1'))->not->toBeNull();
});

it('SentManager::inviteUser() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'u-1', 'email' => 'a@b.com']);
    expect($m->inviteUser('a@b.com', 'A', 'admin'))->not->toBeNull();
});

it('SentManager::removeUser() proxies to default driver', function () {
    extendManagerWithFake()->removeUser('u-1');
    expect(true)->toBeTrue();
});

it('SentManager::listWebhooks() proxies to default driver', function () {
    $m = extendManagerWithFake(['webhooks' => []]);
    expect($m->listWebhooks())->not->toBeNull();
});

it('SentManager::getWebhook() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'wh-1']);
    expect($m->getWebhook('wh-1'))->not->toBeNull();
});

it('SentManager::deleteWebhook() proxies to default driver', function () {
    extendManagerWithFake()->deleteWebhook('wh-1');
    expect(true)->toBeTrue();
});

it('SentManager::toggleWebhook() proxies to default driver', function () {
    $m = extendManagerWithFake(['id' => 'wh-1', 'is_active' => true]);
    expect($m->toggleWebhook('wh-1', true))->not->toBeNull();
});

it('SentManager::rotateWebhookSecret() proxies to default driver', function () {
    $m = extendManagerWithFake(['signing_secret' => 'whsec_new']);
    expect($m->rotateWebhookSecret('wh-1'))->not->toBeNull();
});

// Error paths -----------------------------------------------------------------------

it('SentManager::createDriver() throws for unconfigured connection', function () {
    app(SentManager::class)->connection('nonexistent_xyz');
})->throws(InvalidArgumentException::class, 'nonexistent_xyz');

it('SentManager::dispatch() proxies to default driver', function () {
    Queue::fake();
    $m = extendManagerWithFake();
    $m->dispatch(SentMessage::create()->to('+61412345678')->template('otp'));
    Queue::assertPushed(SendSentMessage::class);
});

it('SentManager::send() proxies to default driver', function () {
    $m = extendManagerWithFake(['status' => 'QUEUED', 'recipients' => []]);
    $result = $m->send(SentMessage::create()->to('+61412345678')->template('otp'));
    expect($result)->not->toBeNull();
});
