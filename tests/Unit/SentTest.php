<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Facades\Sent as SentFacade;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentBulkDispatcher;
use Sujip\SentDm\SentManager;

/**
 * Returns a Sent driver backed by a fake PSR-18 transporter — no real HTTP calls.
 */
function sentWithFakeHttp(): Sent
{
    return sentWithFakeHttpTransporter()[0];
}

/**
 * Same as sentWithFakeHttp(), but also returns the transporter so tests can
 * inspect the last outgoing request's body.
 *
 * @return array{0: Sent, 1: object{lastRequest: ?RequestInterface}}
 */
function sentWithFakeHttpTransporter(): array
{
    $transporter = new class implements ClientInterface
    {
        public ?RequestInterface $lastRequest = null;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->lastRequest = $request;

            return new Response(
                202,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'success' => true,
                    'data' => ['status' => 'QUEUED', 'recipients' => []],
                    'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
                ]) ?: '',
            );
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return [new Sent(new Client(apiKey: 'test-key', requestOptions: $opts)), $transporter];
}

it('Facade resolves to a Sent instance', function () {
    expect(SentFacade::getFacadeRoot())->toBeInstanceOf(SentManager::class);
});

it('to() returns SentMessage with recipient and manager attached', function () {
    $message = sentWithFakeHttp()->to('+61412345678');

    expect($message)->toBeInstanceOf(SentMessage::class)
        ->and($message->getRecipient())->toBe('+61412345678');
});

it('bulk() returns a SentBulkDispatcher', function () {
    expect(sentWithFakeHttp()->bulk(['+61412345678']))->toBeInstanceOf(SentBulkDispatcher::class);
});

it('send() throws when recipient is null', function () {
    sentWithFakeHttp()->send(SentMessage::create());
})->throws(InvalidArgumentException::class);

it('send() calls SDK and returns a response', function () {
    $result = sentWithFakeHttp()->send(
        SentMessage::create()->to('+61412345678')->template('otp')
    );

    expect($result)->not->toBeNull();
});

it('send() accepts template name + id', function () {
    $result = sentWithFakeHttp()->send(
        SentMessage::create()->to('+61412345678')->template('otp', 'tpl-1')
    );

    expect($result)->not->toBeNull();
});

it('send() accepts template parameters', function () {
    $result = sentWithFakeHttp()->send(
        SentMessage::create()->to('+61412345678')->template('otp')->with(['code' => '1234'])
    );

    expect($result)->not->toBeNull();
});

it('send() forwards plain-text content as the text param when no template is set', function () {
    [$sent, $transporter] = sentWithFakeHttpTransporter();

    $sent->send(
        SentMessage::create()->to('+61412345678')->message('Hello world')
    );

    $body = json_decode((string) $transporter->lastRequest->getBody(), true);

    expect($body['text'])->toBe('Hello world')
        ->and($body)->not->toHaveKey('template');
});

it('send() prefers the template over plain-text content when both are set', function () {
    [$sent, $transporter] = sentWithFakeHttpTransporter();

    $sent->send(
        SentMessage::create()->to('+61412345678')->message('Hello world')->template('otp')
    );

    $body = json_decode((string) $transporter->lastRequest->getBody(), true);

    expect($body['template'])->toBe(['name' => 'otp'])
        ->and($body)->not->toHaveKey('text');
});

it('send() accepts a channel override', function () {
    $result = sentWithFakeHttp()->send(
        SentMessage::create()->to('+61412345678')->template('otp')->channel('sms')
    );

    expect($result)->not->toBeNull();
});

it('send() accepts an idempotency key', function () {
    $result = sentWithFakeHttp()->send(
        SentMessage::create()->to('+61412345678')->template('otp')->idempotencyKey('idem-1')
    );

    expect($result)->not->toBeNull();
});

it('send() accepts a profile id', function () {
    $result = sentWithFakeHttp()->send(
        SentMessage::create()->to('+61412345678')->template('otp')->usingProfile('prof-1')
    );

    expect($result)->not->toBeNull();
});

it('dispatch() queues a SendSentMessage job without manager', function () {
    Queue::fake();

    sentWithFakeHttp()->dispatch(
        SentMessage::create()->to('+61412345678')->template('otp')
    );

    Queue::assertPushed(SendSentMessage::class);
});
