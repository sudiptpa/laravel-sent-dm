<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use InvalidArgumentException;
use Sujip\SentDm\Resources\SenderProfiles;
use Sujip\SentDm\Responses\SenderProfileData;

/**
 * Holds a reference to the owning `SenderProfiles` resource, not a raw `Client`, unlike
 * every other builder in this package. `SenderProfiles::submit()` is the internal bridge
 * to `Resource::raw()`, which is protected and shared across the resources that need it.
 *
 * The PATCH schema for update() marks `name` as required in Sent.dm's own OpenAPI spec,
 * but the same schema's own description says the opposite: "Every field is optional, and
 * an omitted field is left alone." That's a self-contradiction in Sent.dm's spec, not
 * something to guess past silently. This builder follows the prose: name is required on
 * create() only, optional on update().
 *
 * @phpstan-import-type SenderProfileShape from SenderProfiles
 */
class SenderProfileBuilder
{
    private ?string $name = null;

    private ?string $shortName = null;

    private ?string $description = null;

    /** @var array<string, mixed>|null */
    private ?array $billing = null;

    /** @var array<string, mixed>|null */
    private ?array $channels = null;

    /** @var array<string, mixed>|null */
    private ?array $compliance = null;

    private ?bool $sandbox = null;

    public function __construct(
        private readonly SenderProfiles $resource,
        private readonly string $mode,
        private readonly ?string $id = null,
        private readonly bool $sandboxDefault = false,
    ) {}

    public function name(string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function shortName(string $shortName): static
    {
        $clone = clone $this;
        $clone->shortName = $shortName;

        return $clone;
    }

    public function description(string $description): static
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    /**
     * `{"inherit": true}` to bill the organization, or the profile's own billing fields.
     * Omit entirely to leave the profile on the storage default (the profile pays).
     *
     * @param  array<string, mixed>  $billing
     */
    public function billing(array $billing): static
    {
        $clone = clone $this;
        $clone->billing = $billing;

        return $clone;
    }

    /**
     * `{"sms": {...}, "whatsapp": {...}, "rcs": {...}}`, each optional. Shape matches
     * `POST /v3/channels/sms`/`/whatsapp`/`/rcs`'s own request bodies (see `Channels`).
     *
     * @param  array<string, mixed>  $channels
     */
    public function channels(array $channels): static
    {
        $clone = clone $this;
        $clone->channels = $channels;

        return $clone;
    }

    /**
     * Members are declared by the market itself. Call `Sent::compliance()->requirements()`
     * for the authoritative set before building this.
     *
     * @param  array<string, mixed>  $compliance
     */
    public function compliance(array $compliance): static
    {
        $clone = clone $this;
        $clone->compliance = $compliance;

        return $clone;
    }

    /**
     * Explicit here always wins over the global `SENT_SANDBOX` config, same as
     * `SentMessage::sandbox()`. Call `sandbox(false)` to force a real save even when
     * `SENT_SANDBOX=true` is set.
     */
    public function sandbox(bool $sandbox = true): static
    {
        $clone = clone $this;
        $clone->sandbox = $sandbox;

        return $clone;
    }

    public function save(): SenderProfileData
    {
        if ($this->mode === 'create' && $this->name === null) {
            throw new InvalidArgumentException('A name is required to create a sender profile. Call name() before save().');
        }

        if ($this->mode === 'create' && $this->shortName === null) {
            throw new InvalidArgumentException('A short name is required to create a sender profile. Call shortName() before save().');
        }

        // Same precedence as Sent::send(): an explicit sandbox(false) call always wins over
        // the global SENT_SANDBOX default, and both normalize to omitted (not `false`) when
        // sandbox is off, so the request body never carries a pointless `sandbox: false`.
        $data = array_filter([
            'name' => $this->name,
            'short_name' => $this->shortName,
            'description' => $this->description,
            'billing' => $this->billing,
            'channels' => $this->channels,
            'compliance' => $this->compliance,
            'sandbox' => ($this->sandbox ?? $this->sandboxDefault) ?: null,
        ], fn (mixed $value): bool => $value !== null);

        if ($this->mode === 'update' && $this->id !== null) {
            return $this->resource->submit('patch', "v3/sender-profiles/{$this->id}", $data);
        }

        return $this->resource->submit('post', 'v3/sender-profiles', $data);
    }
}
