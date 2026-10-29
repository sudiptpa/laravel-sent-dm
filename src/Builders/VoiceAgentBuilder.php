<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use SentDm\Client;
use SentDm\Voice\Agents\APIResponseOfVoiceAgent;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Support\Sandbox;

class VoiceAgentBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $name = null;

    private ?string $sessionType = null;

    private ?string $websocketUrl = null;

    private ?string $greeting = null;

    /** @var array{language?: string|null, setting?: string|null, voice?: string|null}|null */
    private ?array $speech = null;

    /** @var array{sampleRate?: int|null}|null */
    private ?array $audio = null;

    /** @var list<string>|null */
    private ?array $tools = null;

    /** @var list<array{kind?: string|null, value?: string|null}>|null */
    private ?array $transferTargets = null;

    /** @var array{kind?: string|null, value?: string|null}|null */
    private ?array $fallback = null;

    /** @var array{allowedCountries?: list<string>|null, maxCallSeconds?: int|null, maxConcurrentCalls?: int|null}|null */
    private ?array $limits = null;

    /** @var array{retentionDays?: int|null, store?: bool|null}|null */
    private ?array $transcripts = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
        private readonly ?string $profileId = null,
        private readonly bool $sandboxDefault = false,
    ) {}

    public function name(string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function sessionType(string $sessionType): static
    {
        $clone = clone $this;
        $clone->sessionType = $sessionType;

        return $clone;
    }

    public function websocketUrl(string $websocketUrl): static
    {
        $clone = clone $this;
        $clone->websocketUrl = $websocketUrl;

        return $clone;
    }

    public function greeting(string $greeting): static
    {
        $clone = clone $this;
        $clone->greeting = $greeting;

        return $clone;
    }

    /** @param array{language?: string|null, setting?: string|null, voice?: string|null} $speech */
    public function speech(array $speech): static
    {
        $clone = clone $this;
        $clone->speech = $speech;

        return $clone;
    }

    /** @param array{sampleRate?: int|null} $audio */
    public function audio(array $audio): static
    {
        $clone = clone $this;
        $clone->audio = $audio;

        return $clone;
    }

    /** @param list<string> $tools */
    public function tools(array $tools): static
    {
        $clone = clone $this;
        $clone->tools = $tools;

        return $clone;
    }

    /** @param list<array{kind?: string|null, value?: string|null}> $transferTargets */
    public function transferTargets(array $transferTargets): static
    {
        $clone = clone $this;
        $clone->transferTargets = $transferTargets;

        return $clone;
    }

    /** @param array{kind?: string|null, value?: string|null} $fallback */
    public function fallback(array $fallback): static
    {
        $clone = clone $this;
        $clone->fallback = $fallback;

        return $clone;
    }

    /** @param array{allowedCountries?: list<string>|null, maxCallSeconds?: int|null, maxConcurrentCalls?: int|null} $limits */
    public function limits(array $limits): static
    {
        $clone = clone $this;
        $clone->limits = $limits;

        return $clone;
    }

    /** @param array{retentionDays?: int|null, store?: bool|null} $transcripts */
    public function transcripts(array $transcripts): static
    {
        $clone = clone $this;
        $clone->transcripts = $transcripts;

        return $clone;
    }

    public function save(): APIResponseOfVoiceAgent
    {
        $params = [
            'audio' => $this->audio,
            'fallback' => $this->fallback,
            'greeting' => $this->greeting,
            'limits' => $this->limits,
            'name' => $this->name,
            'sandbox' => Sandbox::resolve($this->sandbox, $this->sandboxDefault),
            'sessionType' => $this->sessionType,
            'speech' => $this->speech,
            'tools' => $this->tools,
            'transcripts' => $this->transcripts,
            'transferTargets' => $this->transferTargets,
            'websocketURL' => $this->websocketUrl,
            'idempotencyKey' => $this->idempotencyKey,
            'xProfileID' => $this->profileId,
        ];

        return $this->id === null
            ? $this->client->voice->agents->create(...$params)
            : $this->client->voice->agents->update($this->id, ...$params);
    }
}
