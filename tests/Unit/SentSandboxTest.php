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

// Sent::contacts()/templates()/profiles()/users() used to be constructed without the
// global sandbox default at all, so SENT_SANDBOX=true had no effect on any of them,
// unlike webhooks()/senderProfiles()/channels(). Fixed; these confirm the fix for every
// write path across all four, plus Campaigns and the direct-call (non-builder) mutations.

it('Contacts::create() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->contacts()->create()->phone('+61412345678')->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Contacts::create()->sandbox(false) overrides the global default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->contacts()->create()->phone('+61412345678')->sandbox(false)->save();

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

it('Contacts::update() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->contacts()->update('c-1')->optOut(true)->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Contacts::delete() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->contacts()->delete('c-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Contacts::delete(sandbox: false) overrides the global default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->contacts()->delete('c-1', sandbox: false);

    expect($captured->body['sandbox'] ?? null)->toBeNull();
});

it('Templates::create() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->templates()->create()->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Templates::update() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->templates()->update('tpl-1')->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Templates::delete() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->templates()->delete('tpl-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::create() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->create()->name('Acme')->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::update() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->update('p-1')->name('Acme')->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::delete() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->delete('p-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::complete() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->complete('p-1', 'https://example.com/hook');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::campaigns()->create() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->campaigns('p-1')->create([
        'description' => 'x', 'name' => 'x', 'type' => 'STANDARD',
        'useCases' => [['messagingUseCaseUs' => 'ACCOUNT_NOTIFICATION', 'sampleMessages' => ['hi']]],
    ]);

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::campaigns()->update() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->campaigns('p-1')->update('camp-1', [
        'description' => 'x', 'name' => 'x', 'type' => 'STANDARD',
        'useCases' => [['messagingUseCaseUs' => 'ACCOUNT_NOTIFICATION', 'sampleMessages' => ['hi']]],
    ]);

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Profiles::campaigns()->delete() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->profiles()->campaigns('p-1')->delete('camp-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Users::invite() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->users()->invite()->email('a@example.com')->name('A')->role('developer')->save();

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Users::updateRole() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->users()->updateRole('u-1', 'admin');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Users::remove() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->users()->remove('u-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Webhooks::enable() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->webhooks()->enable('wh-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Webhooks::disable() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->webhooks()->disable('wh-1');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});

it('Webhooks::test() picks up the global sandbox default', function () {
    [$captured, $sent] = capturedSent(globalSandbox: true);
    $sent->webhooks()->test('wh-1', 'message.sent');

    expect($captured->body['sandbox'] ?? null)->toBeTrue();
});
