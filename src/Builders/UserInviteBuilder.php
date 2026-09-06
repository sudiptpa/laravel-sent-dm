<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use SentDm\Client;
use SentDm\Users\UserInviteResponse;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;

class UserInviteBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $email = null;

    private ?string $name = null;

    private ?string $role = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $profileId = null,
        private readonly bool $sandboxDefault = false,
    ) {}

    public function email(string $email): static
    {
        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    public function name(string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function role(string $role): static
    {
        $clone = clone $this;
        $clone->role = $role;

        return $clone;
    }

    public function save(): UserInviteResponse
    {
        return $this->client->users->invite(
            email: $this->email,
            name: $this->name,
            role: $this->role,
            sandbox: ($this->sandbox ?? $this->sandboxDefault) ?: null,
            idempotencyKey: $this->idempotencyKey,
            xProfileID: $this->profileId,
        );
    }
}
