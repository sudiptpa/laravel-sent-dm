<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\RequestOptions;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Sent;

/**
 * Sent.dm's `x-profile-id` header scopes a request to one child profile. Every v3
 * operation accepts it except `/v3/sender-profiles` itself. Resource::profile() sets
 * it; these tests capture the actual outgoing request headers to prove it's sent, both
 * through a typed SDK call (Contacts) and through the raw() escape hatch (Channels).
 *
 * @param  array<string, mixed>|list<mixed>  $data
 * @return array{0: object{headers: ?array<string, mixed>, body: ?string}, 1: Sent}
 */
function capturedSentHeaders(array $data = []): array
{
    $captured = new class
    {
        /** @var array<string, mixed>|null */
        public ?array $headers = null;

        public ?string $body = null;
    };

    $responseBody = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 't', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($captured, $responseBody) implements ClientInterface
    {
        public function __construct(private object $cap, private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            $this->cap->headers = $r->getHeaders();
            $this->cap->body = (string) $r->getBody();

            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(client: new Client(apiKey: 'test', requestOptions: $opts));

    return [$captured, $sent];
}

it('Resource::profile() sends x-profile-id on a typed SDK call', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->contacts()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('no x-profile-id header is sent when profile() was never called', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->contacts()->get();

    expect($captured->headers)->not->toHaveKey('x-profile-id');
});

it('Resource::profile() sends x-profile-id through the raw() escape hatch', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->channels()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('Resource::profile() returns a new instance, leaving the original unscoped', function () {
    $contacts = new Contacts(client: new Client(apiKey: 'test'));
    $scoped = $contacts->profile('child-profile-id');

    expect($scoped)->not->toBe($contacts)
        ->and($scoped)->toBeInstanceOf(Contacts::class);
});
