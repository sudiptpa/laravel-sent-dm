<?php

declare(strict_types=1);

use Sujip\SentDm\Webhooks\VerifySignature;

function signVector(string $key, string $webhookId, string $timestamp, string $rawBody): string
{
    return 'v1,'.base64_encode(hash_hmac('sha256', $webhookId.'.'.$timestamp.'.'.$rawBody, $key, true));
}

it('validates a correctly signed vector', function () {
    $verifier = new VerifySignature;
    $key = 'test-key';
    $timestamp = (string) time();
    $signature = signVector($key, 'wh_1', $timestamp, '{"hello":"world"}');

    expect($verifier->verify($key, 'wh_1', $timestamp, '{"hello":"world"}', $signature))
        ->toBe(VerifySignature::VALID);
});

it('rejects a signature computed with the wrong key', function () {
    $verifier = new VerifySignature;
    $timestamp = (string) time();
    $signature = signVector('wrong-key', 'wh_1', $timestamp, '{}');

    expect($verifier->verify('test-key', 'wh_1', $timestamp, '{}', $signature))
        ->toBe(VerifySignature::INVALID_SIGNATURE);
});

it('rejects a signature computed over a different body', function () {
    $verifier = new VerifySignature;
    $key = 'test-key';
    $timestamp = (string) time();
    $signature = signVector($key, 'wh_1', $timestamp, '{"original":true}');

    expect($verifier->verify($key, 'wh_1', $timestamp, '{"tampered":true}', $signature))
        ->toBe(VerifySignature::INVALID_SIGNATURE);
});

it('rejects a valid signature outside the replay tolerance', function () {
    $verifier = new VerifySignature;
    $key = 'test-key';
    $timestamp = (string) (time() - 600);
    $signature = signVector($key, 'wh_1', $timestamp, '{}');

    expect($verifier->verify($key, 'wh_1', $timestamp, '{}', $signature))
        ->toBe(VerifySignature::STALE_TIMESTAMP);
});

it('decodes a whsec_-prefixed secret from base64', function () {
    $verifier = new VerifySignature;
    $raw = 'a-real-secret';
    $encoded = 'whsec_'.base64_encode($raw);

    expect($verifier->decodeSecret($encoded))->toBe($raw);
});

it('treats a secret without the whsec_ prefix as already raw', function () {
    $verifier = new VerifySignature;

    expect($verifier->decodeSecret('plain-secret'))->toBe('plain-secret');
});

it('returns null for a malformed whsec_ secret', function () {
    $verifier = new VerifySignature;

    expect($verifier->decodeSecret('whsec_!!!not-base64!!!'))->toBeNull();
});
