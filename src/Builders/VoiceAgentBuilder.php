<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use InvalidArgumentException;
use SentDm\Client;
use SentDm\Core\Util;
use SentDm\Voice\Agents\APIResponseOfVoiceAgent;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Support\Sandbox;

class VoiceAgentBuilder
{
    use HasIdempotencyKey, HasSandbox;

    /** @var list<string> keys the live API accepts an explicit null for, to reset the field on update() */
    private const CLEARABLE_FIELDS = [
        'websocketURL', 'greeting', 'fallback', 'speech', 'audio', 'tools', 'limits', 'transcripts', 'transferTargets',
    ];

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

    /** @var list<string> */
    private array $cleared = [];

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
        $clone->cleared = $this->withoutCleared('websocketURL');

        return $clone;
    }

    public function greeting(string $greeting): static
    {
        $clone = clone $this;
        $clone->greeting = $greeting;
        $clone->cleared = $this->withoutCleared('greeting');

        return $clone;
    }

    /** @param array{language?: string|null, setting?: string|null, voice?: string|null} $speech */
    public function speech(array $speech): static
    {
        $clone = clone $this;
        $clone->speech = $speech;
        $clone->cleared = $this->withoutCleared('speech');

        return $clone;
    }

    /** @param array{sampleRate?: int|null} $audio */
    public function audio(array $audio): static
    {
        $clone = clone $this;
        $clone->audio = $audio;
        $clone->cleared = $this->withoutCleared('audio');

        return $clone;
    }

    /** @param list<string> $tools */
    public function tools(array $tools): static
    {
        $clone = clone $this;
        $clone->tools = $tools;
        $clone->cleared = $this->withoutCleared('tools');

        return $clone;
    }

    /** @param list<array{kind?: string|null, value?: string|null}> $transferTargets */
    public function transferTargets(array $transferTargets): static
    {
        $clone = clone $this;
        $clone->transferTargets = $transferTargets;
        $clone->cleared = $this->withoutCleared('transferTargets');

        return $clone;
    }

    /** @param array{kind?: string|null, value?: string|null} $fallback */
    public function fallback(array $fallback): static
    {
        $clone = clone $this;
        $clone->fallback = $fallback;
        $clone->cleared = $this->withoutCleared('fallback');

        return $clone;
    }

    /** @param array{allowedCountries?: list<string>|null, maxCallSeconds?: int|null, maxConcurrentCalls?: int|null} $limits */
    public function limits(array $limits): static
    {
        $clone = clone $this;
        $clone->limits = $limits;
        $clone->cleared = $this->withoutCleared('limits');

        return $clone;
    }

    /** @param array{retentionDays?: int|null, store?: bool|null} $transcripts */
    public function transcripts(array $transcripts): static
    {
        $clone = clone $this;
        $clone->transcripts = $transcripts;
        $clone->cleared = $this->withoutCleared('transcripts');

        return $clone;
    }

    /**
     * Resets a field to its default on update(): the live API documents a key sent as an
     * explicit null this way, distinct from leaving the key out (unchanged). The SDK's
     * typed update() strips every null field before sending (same issue as
     * voice()->unbindAgent() in Voice.php), so save() routes through the SDK's raw service
     * instead, which converts keys to their wire names without stripping nulls.
     */
    public function clear(string $field): static
    {
        if (! in_array($field, self::CLEARABLE_FIELDS, true)) {
            throw new InvalidArgumentException("'{$field}' is not a clearable voice agent field.");
        }

        $clone = clone $this;
        $clone->cleared = [...$this->withoutCleared($field), $field];

        return $clone;
    }

    /** @return list<string> */
    private function withoutCleared(string $field): array
    {
        return array_values(array_filter($this->cleared, fn (string $cleared): bool => $cleared !== $field));
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

        if ($this->id === null) {
            return $this->client->voice->agents->create(...$params);
        }

        if ($this->cleared === []) {
            return $this->client->voice->agents->update($this->id, ...$params);
        }

        // raw->update() has no Util::removeNulls() pass, so every key in $params reaches
        // the wire as sent. Only the touched top-level fields survive here, each still run
        // through removeNulls() so a nested null (e.g. limits.maxConcurrentCalls) doesn't
        // reach the wire just because a sibling field was cleared, then the cleared fields
        // are added back as explicit top-level nulls.
        $touched = array_map(
            static fn (mixed $value): mixed => Util::removeNulls($value),
            array_filter($params, static fn (mixed $value): bool => $value !== null),
        );

        foreach ($this->cleared as $field) {
            $touched[$field] = null;
        }

        // @phpstan-ignore-next-line argument.type
        return $this->client->voice->agents->raw->update($this->id, params: $touched)->parse();
    }
}
