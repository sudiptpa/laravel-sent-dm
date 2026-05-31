<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use SentDm\Client;
use SentDm\Users\APIResponseOfUser;

class UserInviteBuilder
{
    private ?string $email = null;

    private ?string $name = null;

    private ?string $role = null;

    public function __construct(private readonly Client $client) {}

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

    public function save(): APIResponseOfUser
    {
        return $this->client->users->invite(
            email: $this->email,
            name: $this->name,
            role: $this->role,
        );
    }
}
