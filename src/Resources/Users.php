<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Users\APIResponseOfUser;
use SentDm\Users\UserListResponse;
use Sujip\SentDm\Builders\UserInviteBuilder;
use Sujip\SentDm\Support\Sandbox;

class Users extends Resource
{
    public function get(): UserListResponse
    {
        return $this->client->users->list(xProfileID: $this->orgProfileId);
    }

    public function find(string $id): APIResponseOfUser
    {
        return $this->client->users->retrieve(userID: $id, xProfileID: $this->orgProfileId);
    }

    public function invite(): UserInviteBuilder
    {
        return new UserInviteBuilder(client: $this->client, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function updateRole(string $id, string $role, ?string $idempotencyKey = null, ?bool $sandbox = null): APIResponseOfUser
    {
        return $this->client->users->updateRole(
            userID: $id,
            role: $role,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function remove(string $id, ?bool $sandbox = null): void
    {
        $this->client->users->remove(
            userID: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
    }
}
