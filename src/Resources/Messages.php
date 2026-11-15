<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Core\Conversion;
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

    public function cancel(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): APIResponseOfMessage
    {
        $body = [];
        $resolvedSandbox = Sandbox::resolve($sandbox, $this->sandbox);

        if ($resolvedSandbox !== null) {
            $body['sandbox'] = $resolvedSandbox;
        }

        $data = $this->raw(
            'post',
            "v3/messages/{$id}/cancel",
            body: $body,
            headers: $this->idempotencyHeader($idempotencyKey),
        );

        /** @var APIResponseOfMessage */
        return Conversion::coerce(APIResponseOfMessage::class, [
            'success' => true,
            'data' => $data,
        ]);
    }
}
