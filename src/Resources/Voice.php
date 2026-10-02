<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Channels\Voice\APIResponseOfListOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceCallbackTest;
use SentDm\Channels\Voice\APIResponseOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceNumberCreated;
use SentDm\Channels\Voice\APIResponseOfVoiceSecret;
use SentDm\Channels\Voice\APIResponseOfVoiceToken;
use SentDm\Channels\Voice\VoiceUpdateParams\Status;
use Sujip\SentDm\Support\Sandbox;

class Voice extends Resource
{
    public function create(
        string $callbackUrl,
        ?string $number = null,
        ?string $areaCode = null,
        ?bool $defaultForAppCalls = null,
        ?bool $sandbox = null,
        ?string $idempotencyKey = null,
    ): APIResponseOfVoiceNumberCreated {
        return $this->client->channels->voice->create(
            callbackURL: $callbackUrl,
            areaCode: $areaCode,
            defaultForAppCalls: $defaultForAppCalls,
            number: $number,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function retrieve(string $number): APIResponseOfVoiceNumber
    {
        return $this->client->channels->voice->retrieve(number: $number, xProfileID: $this->orgProfileId);
    }

    public function update(
        string $number,
        ?string $callbackUrl = null,
        Status|string|null $status = null,
        ?bool $defaultForAppCalls = null,
        ?bool $sandbox = null,
        ?string $idempotencyKey = null,
    ): APIResponseOfVoiceNumber {
        return $this->client->channels->voice->update(
            number: $number,
            callbackURL: $callbackUrl,
            defaultForAppCalls: $defaultForAppCalls,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            status: $status instanceof Status ? $status : ($status !== null ? Status::from(strtoupper($status)) : null),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function list(): APIResponseOfListOfVoiceNumber
    {
        return $this->client->channels->voice->list(xProfileID: $this->orgProfileId);
    }

    public function createToken(
        string $identity,
        ?string $number = null,
        ?int $ttl = null,
        ?bool $sandbox = null,
        ?string $idempotencyKey = null,
    ): APIResponseOfVoiceToken {
        return $this->client->channels->voice->createToken(
            identity: $identity,
            number: $number,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            ttl: $ttl,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function rotateSecret(string $number, ?bool $sandbox = null, ?string $idempotencyKey = null): APIResponseOfVoiceSecret
    {
        return $this->client->channels->voice->rotateSecret(
            number: $number,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function test(string $number, ?bool $sandbox = null, ?string $idempotencyKey = null): APIResponseOfVoiceCallbackTest
    {
        return $this->client->channels->voice->test(
            number: $number,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }
}
