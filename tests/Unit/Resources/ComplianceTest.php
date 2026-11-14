<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\Compliance\APIResponseOfComplianceRequirements;
use SentDm\Core\Exceptions\BadRequestException;
use SentDm\Core\Exceptions\InternalServerException;
use SentDm\RequestOptions;
use Sujip\SentDm\Sent;

function sampleRequirements(): array
{
    return [
        'channel' => 'sms',
        'country' => 'US',
        'type' => 'TEN_DLC',
        'required' => true,
        'sender_id_pattern' => null,
        'requirements' => [['key' => 'brand.legal_name', 'label' => 'Legal name', 'required' => true]],
        'setup' => [['step' => 1, 'resource' => 'sender', 'method' => 'POST', 'path' => '/v3/channels/sms']],
    ];
}

it('compliance()->requirements() defaults to the sms channel', function () {
    [$captured, $sent] = capturedSentHeaders(sampleRequirements());

    $result = $sent->compliance()->requirements('US', 'TEN_DLC');

    parse_str((string) parse_url((string) $captured->uri, PHP_URL_QUERY), $query);

    expect($result)->toBeInstanceOf(APIResponseOfComplianceRequirements::class)
        ->and($captured->method)->toBe('GET')
        ->and(parse_url((string) $captured->uri, PHP_URL_PATH))->toBe('/v3/compliance/requirements')
        ->and($query)->toBe(['channel' => 'sms', 'country' => 'US', 'type' => 'TEN_DLC'])
        ->and($result->data->channel)->toBe('sms')
        ->and($result->data->country)->toBe('US')
        ->and($result->data->type)->toBe('TEN_DLC')
        ->and($result->data->required)->toBeTrue()
        ->and($result->data->senderIDPattern)->toBeNull()
        ->and($result->data->requirements[0]->key)->toBe('brand.legal_name')
        ->and($result->data->requirements[0]->label)->toBe('Legal name')
        ->and($result->data->setup[0]->step)->toBe(1)
        ->and($result->data->setup[0]->path)->toBe('/v3/channels/sms');
});

it('compliance()->requirements() accepts an explicit channel override', function () {
    [$captured, $sent] = capturedSentHeaders(sampleRequirements());

    $sent->compliance()->requirements('US', 'TEN_DLC', 'whatsapp');

    parse_str((string) parse_url((string) $captured->uri, PHP_URL_QUERY), $query);

    expect($query['channel'])->toBe('whatsapp');
});

it('compliance()->profile() scopes the request to a child profile', function () {
    [$captured, $sent] = capturedSentHeaders(sampleRequirements());

    $sent->compliance()->profile('prof-1')->requirements('US', 'TEN_DLC');

    expect($captured->headers['x-profile-id'] ?? [])->toBe(['prof-1']);
});

it('compliance()->requirements() leaves an absent optional field unset', function () {
    $result = sentApi(['channel' => 'sms', 'country' => 'GB', 'type' => 'ALPHANUMERIC'])
        ->compliance()->requirements('GB', 'ALPHANUMERIC');

    expect(isset($result->data->requirements))->toBeFalse()
        ->and($result->data->requirements ?? [])->toBe([]);
});

it('compliance()->requirements() lets the SDK exception through', function (int $status, string $exception) {
    $transporter = new class($status) implements ClientInterface
    {
        public function __construct(private int $status) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response($this->status, ['Content-Type' => 'application/json'], json_encode([
                'success' => false,
                'error' => ['code' => 'BUSINESS_009', 'message' => 'Not available'],
                'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
            ]) ?: '{}');
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    expect(fn () => $sent->compliance()->requirements('US', 'TEN_DLC', 'whatsapp'))->toThrow($exception);
})->with([
    'not available for the channel' => [501, InternalServerException::class],
    'missing country or type' => [400, BadRequestException::class],
]);
