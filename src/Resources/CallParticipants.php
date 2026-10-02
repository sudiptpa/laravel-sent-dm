<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SentDm\Calls\APIResponseOfCall;
use SentDm\Calls\Participants\APIResponseOfListOfCallParticipant;
use SentDm\Calls\Participants\CallParticipantTarget;
use SentDm\Client;
use Sujip\SentDm\Support\Sandbox;

/**
 * @phpstan-import-type CallParticipantTargetShape from CallParticipantTarget
 */
class CallParticipants extends Resource
{
    public function __construct(
        Client $client,
        private readonly string $callId,
        ?CacheRepository $cache = null,
        bool $cacheEnabled = false,
        int $cacheTtl = 3600,
        bool $sandbox = false,
        string $connectionName = 'default',
    ) {
        parent::__construct($client, $cache, $cacheEnabled, $cacheTtl, $sandbox, $connectionName);
    }

    public function list(): APIResponseOfListOfCallParticipant
    {
        return $this->client->calls->participants->list(
            id: $this->callId,
            xProfileID: $this->orgProfileId,
        );
    }

    /**
     * @param  CallParticipantTarget|CallParticipantTargetShape  $to
     */
    public function add(CallParticipantTarget|array $to, ?string $callerId = null, ?bool $sandbox = null, ?string $idempotencyKey = null): APIResponseOfCall
    {
        return $this->client->calls->participants->add(
            id: $this->callId,
            callerID: $callerId,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            to: $to,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function update(string $participantId, bool $muted, ?bool $sandbox = null, ?string $idempotencyKey = null): mixed
    {
        return $this->client->calls->participants->update(
            participantID: $participantId,
            id: $this->callId,
            muted: $muted,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function remove(string $participantId, ?bool $sandbox = null): mixed
    {
        return $this->client->calls->participants->remove(
            participantID: $participantId,
            id: $this->callId,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
    }

    public function removeAll(?bool $sandbox = null): mixed
    {
        return $this->client->calls->participants->removeAll(
            id: $this->callId,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
    }
}
