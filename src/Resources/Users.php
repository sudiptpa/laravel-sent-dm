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
        return $this->client->users->list();
    }

    public function find(string $id): UserGetResponse
    {
        return $this->client->users->retrieve(userID: $id);
    }

    public function invite(): UserInviteBuilder
    {
        return new UserInviteBuilder(client: $this->client);
    }

    public function updateRole(string $id, string $role): UserUpdateRoleResponse
    {
        return $this->client->users->updateRole(userID: $id, role: $role);
    }

    public function remove(string $id): void
    {
        $this->client->users->remove(userID: $id);
    }
}
