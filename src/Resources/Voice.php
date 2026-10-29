<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Channels\Voice\APIResponseOfListOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceCallbackTest;
use SentDm\Channels\Voice\APIResponseOfVoiceNumber;
use SentDm\Channels\Voice\APIResponseOfVoiceNumberCreated;
use SentDm\Channels\Voice\APIResponseOfVoiceSecret;
use SentDm\Channels\Voice\VoiceUpdateParams\Status;
use SentDm\Voice\Tokens\APIResponseOfVoiceToken;
use Sujip\SentDm\Support\Sandbox;

class Voice extends Resource
{
    public function create(
        ?string $callbackUrl = null,
        ?string $number = null,
        ?string $areaCode = null,
        ?bool $defaultForAppCalls = null,
        ?bool $sandbox = null,
        ?string $idempotencyKey = null,
        ?string $agentId = null,
    ): APIResponseOfVoiceNumberCreated {
        return $this->client->channels->voice->create(
            agentID: $agentId,
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
        ?string $agentId = null,
        bool $unbindAgent = false,
    ): APIResponseOfVoiceNumber {
        if ($unbindAgent) {
            return $this->updateUnbindingAgent($number, $callbackUrl, $status, $defaultForAppCalls, $sandbox, $idempotencyKey);
        }

        return $this->client->channels->voice->update(
            number: $number,
            agentID: $agentId,
            callbackURL: $callbackUrl,
            defaultForAppCalls: $defaultForAppCalls,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            status: $status instanceof Status ? $status : ($status !== null ? Status::from(strtoupper($status)) : null),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * The SDK's typed update() strips null body fields (SentDm\Core\Util::removeNulls()),
     * so "agent_id": null never reaches the wire and the agent is never unbound — confirmed
     * live, see sdk-issues.md. This builds the request by hand so the explicit null survives.
     */
    private function updateUnbindingAgent(
        string $number,
        ?string $callbackUrl,
        Status|string|null $status,
        ?bool $defaultForAppCalls,
        ?bool $sandbox,
        ?string $idempotencyKey,
    ): APIResponseOfVoiceNumber {
        $resolvedStatus = $status instanceof Status ? $status : ($status !== null ? Status::from(strtoupper($status)) : null);
        $resolvedSandbox = Sandbox::resolve($sandbox, $this->sandbox);

        $body = array_filter([
            'callback_url' => $callbackUrl,
            'status' => $resolvedStatus?->value,
            'default_for_app_calls' => $defaultForAppCalls,
            'sandbox' => $resolvedSandbox,
        ], static fn (mixed $value): bool => $value !== null);
        $body['agent_id'] = null;

        $headers = $this->idempotencyHeader($idempotencyKey);
        if ($this->orgProfileId !== null) {
            $headers += ['x-profile-id' => $this->orgProfileId];
        }

        // Not routed through Resource::raw(): that helper's unwrap: 'data' + convert: 'mixed'
        // combo returns an untranslated array, and APIResponseOfVoiceNumber's generated
        // fromArray() does not map snake_case keys (routes_to, callback_url, ...) back to
        // their camelCase properties — only the SDK's own attribute-aware envelope
        // deserialization does that, so convert: APIResponseOfVoiceNumber::class is used
        // directly here, the same way the typed update() method itself calls it.
        // @phpstan-ignore-next-line return.type
        return $this->client->request(
            method: 'patch',
            path: ['v3/channels/voice/%1$s', $number],
            headers: $headers,
            body: (object) $body,
            convert: APIResponseOfVoiceNumber::class,
        )->parse();
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
        return $this->client->voice->tokens->create(
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
