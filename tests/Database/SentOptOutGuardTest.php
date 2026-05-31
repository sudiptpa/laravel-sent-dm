<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Exceptions\ContactOptedOutException;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Sent;

function sentWithGuard(): Sent
{
    $transporter = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'],
                '{"success":true,"data":{"status":"QUEUED","recipients":[]},"meta":{"request_id":"t","timestamp":"2025-01-01T00:00:00Z","version":"v3"}}');
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return new Sent(
        client: new Client(apiKey: 'test', requestOptions: $opts),
        cache: new Repository(new ArrayStore),
        cacheEnabled: false,
        optOutGuard: true,
    );
}

it('send() throws ContactOptedOutException when contact has opted out', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    sentWithGuard()->send(SentMessage::create()->to('+61412345678')->template('otp'));
})->throws(ContactOptedOutException::class);

it('send() succeeds when contact is not opted out', function () {
    $result = sentWithGuard()->send(SentMessage::create()->to('+61412345678')->template('otp'));

    expect($result)->not->toBeNull();
});

it('send() succeeds when contact has opted in after opting out', function () {
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => false]);

    $result = sentWithGuard()->send(SentMessage::create()->to('+61412345678')->template('otp'));

    expect($result)->not->toBeNull();
});

it('dispatch() always queues the job — opt-out is enforced inside the job', function () {
    Queue::fake();
    SentOptOut::create(['phone_number' => '+61412345678', 'opted_out' => true]);

    // dispatch() does not check opt-out — the queued job catches ContactOptedOutException
    // from send() and calls fail(), preserving the "sendLater never blocks" contract.
    sentWithGuard()->dispatch(SentMessage::create()->to('+61412345678')->template('otp'));

    Queue::assertPushed(SendSentMessage::class);
});

it('send() skips guard when recipient is null', function () {
    // Should throw InvalidArgumentException (no recipient), not ContactOptedOutException
    sentWithGuard()->send(SentMessage::create()->template('otp'));
})->throws(InvalidArgumentException::class);
