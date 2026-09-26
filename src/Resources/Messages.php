<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Messages\MessageGetActivitiesResponse;
use SentDm\Messages\MessageGetStatusResponse;
use SentDm\Messages\MessageSendResponse;
use Sujip\SentDm\Support\Sandbox;

class Messages extends Resource
{
    public function retrieve(string $id): MessageGetStatusResponse
    {
        return $this->client->messages->retrieveStatus(id: $id, xProfileID: $this->orgProfileId);
    }

    public function activities(string $id): MessageGetActivitiesResponse
    {
        return $this->client->messages->retrieveActivities(id: $id, xProfileID: $this->orgProfileId);
    }

    public function resend(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): MessageSendResponse
    {
        $body = [];
        $resolvedSandbox = Sandbox::resolve($sandbox, $this->sandbox);

        if ($resolvedSandbox !== null) {
            $body['sandbox'] = $resolvedSandbox;
        }

        $data = $this->raw(
            'post',
            "v3/messages/{$id}/resend",
            body: $body,
            headers: $this->idempotencyHeader($idempotencyKey),
        );

        if (array_key_exists('template_id', $data)) {
            $data['templateID'] = $data['template_id'];
            unset($data['template_id']);
        }

        if (array_key_exists('template_name', $data)) {
            $data['templateName'] = $data['template_name'];
            unset($data['template_name']);
        }

        if (isset($data['recipients']) && is_array($data['recipients'])) {
            foreach ($data['recipients'] as $key => $recipient) {
                if (! is_array($recipient) || ! array_key_exists('message_id', $recipient)) {
                    continue;
                }

                $recipient['messageID'] = $recipient['message_id'];
                unset($recipient['message_id']);
                $data['recipients'][$key] = $recipient;
            }
        }

        return MessageSendResponse::fromArray([
            'success' => true,
            'data' => $data,
        ]);
    }
}
