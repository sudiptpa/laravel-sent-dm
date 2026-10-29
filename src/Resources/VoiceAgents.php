<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Voice\Agents\APIResponseOfVoiceAgent;
use SentDm\Voice\Agents\APIResponseOfVoiceAgentSecret;
use SentDm\Voice\Agents\VoiceAgent;
use SentDm\VoiceAgentsPage;
use Sujip\SentDm\Builders\VoiceAgentBuilder;
use Sujip\SentDm\Concerns\Paginatable;
use Sujip\SentDm\Support\Sandbox;

class VoiceAgents extends Resource
{
    use Paginatable;

    /** @return VoiceAgentsPage<VoiceAgent> */
    public function get(): VoiceAgentsPage
    {
        return $this->client->voice->agents->list(
            page: $this->page,
            pageSize: $this->pageSize,
            xProfileID: $this->orgProfileId,
        );
    }

    public function find(string $id): APIResponseOfVoiceAgent
    {
        return $this->client->voice->agents->retrieve(id: $id, xProfileID: $this->orgProfileId);
    }

    public function create(): VoiceAgentBuilder
    {
        return new VoiceAgentBuilder($this->client, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function update(string $id): VoiceAgentBuilder
    {
        return new VoiceAgentBuilder($this->client, id: $id, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function delete(string $id, ?bool $sandbox = null): mixed
    {
        return $this->client->voice->agents->delete(
            id: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
    }

    public function rotateSecret(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): APIResponseOfVoiceAgentSecret
    {
        return $this->client->voice->agents->rotateSecret(
            id: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }
}
