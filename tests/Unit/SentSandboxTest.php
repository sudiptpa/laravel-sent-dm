<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;

function capturedSent(bool $globalSandbox = false): array
{
    $captured = new class
    {
        public ?array $body = null;
    };

    $transporter = new class($captured) implements ClientInterface
    {
        public function __construct(private object $cap) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->cap->body = json_decode((string) $r->getBody(), true) ?? [];

            return new Response(202, ['Content-Type' => 'application/json'],
                '{"success":true,"data":{"status":"QUEUED","recipients":[]},"meta":{"request_id":"t","timestamp":"2025-01-01T00:00:00Z","version":"v3"}}');
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        sandbox: $globalSandbox,
    );

    return [$captured, $sent];
}

it('SentMessage::sandbox() flag is forwarded to the SDK', function () {
    [$captured, $sent] = capturedSent();
    $sent->send(SentMessage::create()->to('+61412345678')->template('otp')->sandbox());

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('sandbox not set when neither global nor message sandbox is enabled', function () {
    [$captured, $sent] = capturedSent();
    $sent->send(SentMessage::create()->to('+61412345678')->template('otp'));

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

it('global sandbox=true forwards sandbox flag even without SentMessage::sandbox()', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->send(SentMessage::create()->to('+61412345678')->template('otp'));

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('message sandbox(false) overrides driver-level sandbox=true', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->send(SentMessage::create()->to('+61412345678')->template('otp')->sandbox(false));

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

// The same precedence must hold for every resource that accepts sandbox, not just
// Messages: SENT_SANDBOX applies globally unless a call explicitly overrides it.

it('SenderProfiles::create() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->senderProfiles()->create()->name('Test')->shortName('TST')->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('SenderProfiles::create()->sandbox(false) overrides the global default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->senderProfiles()->create()->name('Test')->shortName('TST')->sandbox(false)->save();

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

it('SenderProfiles::create() sandbox is not set when global is off and sandbox() was never called', function () {
    [$captured, $sent] = capturedSent();
    $sent->senderProfiles()->create()->name('Test')->shortName('TST')->save();

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

it('Channels::addRcs() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->channels()->addRcs([
        'brand_name' => 'Test', 'privacy_policy_url' => 'https://example.com/privacy',
        'terms_and_conditions_url' => 'https://example.com/terms',
    ]);

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Channels::addRcs() explicit sandbox key overrides the global default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->channels()->addRcs([
        'brand_name' => 'Test', 'privacy_policy_url' => 'https://example.com/privacy',
        'terms_and_conditions_url' => 'https://example.com/terms', 'sandbox' => false,
    ]);

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

it('Webhooks::create() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->webhooks()->create()->name('Test')->url('https://example.com/wh')->events(['message'])->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Webhooks::create()->sandbox(false) overrides the global default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->webhooks()->create()->name('Test')->url('https://example.com/wh')->events(['message'])->sandbox(false)->save();

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});
