<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Users\UserGetResponse;
use SentDm\Users\UserListResponse;
use SentDm\Users\UserUpdateRoleResponse;
use Sujip\SentDm\Builders\UserInviteBuilder;

class Users extends Resource
{
    public function get(): UserListResponse
    {
        return $this->client->users->list(xProfileID: $this->orgProfileId);
    }

    public function find(string $id): UserGetResponse
    {
        return $this->client->users->retrieve(userID: $id, xProfileID: $this->orgProfileId);
    }

    public function invite(): UserInviteBuilder
    {
        return new UserInviteBuilder(client: $this->client, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function updateRole(string $id, string $role, ?string $idempotencyKey = null, ?bool $sandbox = null): UserUpdateRoleResponse
    {
        return $this->client->users->updateRole(
            userID: $id,
            role: $role,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function remove(string $id, ?bool $sandbox = null): void
    {
        $this->client->users->remove(
            userID: $id,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            xProfileID: $this->orgProfileId,
        );
    }
}
