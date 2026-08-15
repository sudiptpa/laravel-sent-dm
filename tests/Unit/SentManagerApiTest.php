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
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Conversations;
use Sujip\SentDm\Resources\Messages;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

/**
 * Register a fake Sent driver on the real SentManager via extend() so that
 * all proxy calls go through the real SentManager class methods — coverage recorded.
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

// Messaging proxies ----------------------------------------------------------

it('SentManager::to() proxies to default driver', function () {
    expect(extendManagerWithFake()->to('+61412345678'))->not->toBeNull();
});

it('SentManager::bulk() proxies to default driver', function () {
    expect(extendManagerWithFake()->bulk(['+61412345678']))->not->toBeNull();
});

it('SentManager::send() proxies to default driver', function () {
    $result = extendManagerWithFake(['status' => 'QUEUED', 'recipients' => []])
        ->send(SentMessage::create()->to('+61412345678')->template('otp'));
    expect($result)->not->toBeNull();
});

it('SentManager::dispatch() proxies to default driver', function () {
    Queue::fake();
    extendManagerWithFake()->dispatch(SentMessage::create()->to('+61412345678')->template('otp'));
    Queue::assertPushed(SendSentMessage::class);
});

// Account + lookup -----------------------------------------------------------

it('SentManager::account() proxies to default driver', function () {
    expect(
        extendManagerWithFake(['type' => 'organization', 'name' => 'Acme', 'email' => 'a@b.com'])->account()
    )->not->toBeNull();
});

it('SentManager::lookup() proxies to default driver', function () {
    expect(
        extendManagerWithFake(['isValid' => true, 'carrierName' => 'Telstra'])->lookup('+61412345678')
    )->not->toBeNull();
});

// Resource proxies -----------------------------------------------------------

it('SentManager::messages() returns a Messages resource', function () {
    expect(extendManagerWithFake()->messages())->toBeInstanceOf(Messages::class);
});

it('SentManager::contacts() returns a Contacts resource', function () {
    expect(extendManagerWithFake()->contacts())->toBeInstanceOf(Contacts::class);
});

it('SentManager::conversations() returns a Conversations resource', function () {
    expect(extendManagerWithFake()->conversations())->toBeInstanceOf(Conversations::class);
});

it('SentManager::templates() returns a Templates resource', function () {
    expect(extendManagerWithFake()->templates())->toBeInstanceOf(Templates::class);
});

it('SentManager::webhooks() returns a Webhooks resource', function () {
    expect(extendManagerWithFake()->webhooks())->toBeInstanceOf(Webhooks::class);
});

it('SentManager::profiles() returns a Profiles resource', function () {
    expect(extendManagerWithFake()->profiles())->toBeInstanceOf(Profiles::class);
});

it('SentManager::users() returns a Users resource', function () {
    expect(extendManagerWithFake()->users())->toBeInstanceOf(Users::class);
});

// Error paths ----------------------------------------------------------------

it('SentManager::createDriver() throws for unconfigured connection', function () {
    app(SentManager::class)->connection('nonexistent_xyz');
})->throws(InvalidArgumentException::class, 'nonexistent_xyz');
