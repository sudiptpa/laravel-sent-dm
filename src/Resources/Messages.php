<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Messages\APIResponseOfCancelledMessage;
use SentDm\Messages\APIResponseOfMessage;
use SentDm\Messages\APIResponseOfMessageActivities;
use SentDm\Messages\MessageSendResponse;
use Sujip\SentDm\Support\Sandbox;

class Messages extends Resource
{
    public function retrieve(string $id): APIResponseOfMessage
    {
        return $this->client->messages->retrieveStatus(id: $id, xProfileID: $this->orgProfileId);
    }

    public function activities(string $id): APIResponseOfMessageActivities
    {
        return $this->client->messages->retrieveActivities(id: $id, xProfileID: $this->orgProfileId);
    }

    public function resend(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): MessageSendResponse
    {
        return $this->client->messages->resend(
            id: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function cancel(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): APIResponseOfCancelledMessage
    {
        return $this->client->messages->cancel(
            id: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }
}
