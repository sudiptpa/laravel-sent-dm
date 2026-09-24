<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Psr\Http\Message\RequestInterface;
use SentDm\Core\Exceptions\APIConnectionException;
use SentDm\Core\Exceptions\APIStatusException;
use SentDm\Numbers\NumberLookupResponse;
use SentDm\Numbers\NumberLookupResponse\Data;
use Sujip\SentDm\Rules\SentMobileNumber;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeNumberResponse(bool $valid, string $lineType = 'mobile'): NumberLookupResponse
{
    $data = new Data;
    $data['isValid'] = $valid;
    $data['lineType'] = $lineType;

    $response = new NumberLookupResponse;
    $response['data'] = $data;

    return $response;
}

it('Rule macro returns a SentMobileNumber instance', function () {
    expect(Rule::sentMobileNumber())->toBeInstanceOf(SentMobileNumber::class);
});

it('Rule macro accepts connection and requireMobile args', function () {
    $rule = Rule::sentMobileNumber(connection: 'tenant_a', requireMobile: true);
    expect($rule)->toBeInstanceOf(SentMobileNumber::class);
});

it('fails immediately for non-E.164 format', function () {
    $validator = Validator::make(
        ['phone' => '0412345678'],
        ['phone' => [new SentMobileNumber]]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('phone'))->toContain('E.164');
});

it('fails for E.164 that is too short', function () {
    $validator = Validator::make(
        ['phone' => '+123'],
        ['phone' => [new SentMobileNumber]]
    );

    expect($validator->fails())->toBeTrue();
});

it('passes for a valid mobile number from the API', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andReturn(fakeNumberResponse(true, 'mobile'));
    app()->instance(SentManager::class, mockSentManager($driver));

    $validator = Validator::make(
        ['phone' => '+61412345678'],
        ['phone' => [new SentMobileNumber]]
    );

    expect($validator->passes())->toBeTrue();
});

it('fails when API says number is not valid', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andReturn(fakeNumberResponse(false));
    app()->instance(SentManager::class, mockSentManager($driver));

    $validator = Validator::make(
        ['phone' => '+61412345678'],
        ['phone' => [new SentMobileNumber]]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('phone'))->toContain('valid');
});

it('fails when requireMobile and line type is landline', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andReturn(fakeNumberResponse(true, 'landline'));
    app()->instance(SentManager::class, mockSentManager($driver));

    $validator = Validator::make(
        ['phone' => '+61212345678'],
        ['phone' => [new SentMobileNumber(requireMobile: true)]]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('phone'))->toContain('mobile');
});

it('passes when requireMobile is false and line type is landline', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andReturn(fakeNumberResponse(true, 'landline'));
    app()->instance(SentManager::class, mockSentManager($driver));

    $validator = Validator::make(
        ['phone' => '+61212345678'],
        ['phone' => [new SentMobileNumber]]
    );

    expect($validator->passes())->toBeTrue();
});

it('fails open when the lookup API throws', function () {
    $driver = Mockery::mock(Sent::class);
    $request = Mockery::mock(RequestInterface::class);
    $driver->shouldReceive('lookup')->once()->andThrow(new APIConnectionException($request, message: 'network error'));
    app()->instance(SentManager::class, mockSentManager($driver));

    $validator = Validator::make(
        ['phone' => '+61412345678'],
        ['phone' => [new SentMobileNumber]]
    );

    // API down must not fail validation
    expect($validator->passes())->toBeTrue();
});

it('fails when data is null in API response', function () {
    $response = new NumberLookupResponse;

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andReturn($response);
    app()->instance(SentManager::class, mockSentManager($driver));

    $validator = Validator::make(
        ['phone' => '+61412345678'],
        ['phone' => [new SentMobileNumber]]
    );

    expect($validator->fails())->toBeTrue();
});

it('surfaces configuration and programming errors during lookup', function (Throwable $error) {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andThrow($error);
    app()->instance(SentManager::class, mockSentManager($driver));

    expect(fn () => Validator::make(['phone' => '+14155550101'], ['phone' => [new SentMobileNumber]])->passes())
        ->toThrow($error::class);
})->with([
    'configuration' => [new InvalidArgumentException('Connection is not configured.')],
    'programming' => [new TypeError('Invalid response type.')],
    'credentials' => [APIStatusException::from(new Request('GET', 'https://example.com'), new Response(401, [], '{}'))],
]);

it('fails open during temporary lookup outages', function (int $status) {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('lookup')->once()->andThrow(
        APIStatusException::from(new Request('GET', 'https://example.com'), new Response($status, [], '{}')),
    );
    app()->instance(SentManager::class, mockSentManager($driver));

    expect(Validator::make(['phone' => '+14155550101'], ['phone' => [new SentMobileNumber]])->passes())->toBeTrue();
})->with([429, 503]);
