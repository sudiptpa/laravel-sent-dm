<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

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

        return APIResponseOfMessage::fromArray([
            'success' => true,
            'data' => self::statusResponseDataFromRawData($data),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function statusResponseDataFromRawData(array $data): array
    {
        $fields = [
            'active_contact_price' => 'activeContactPrice',
            'contact_id' => 'contactID',
            'created_at' => 'createdAt',
            'customer_id' => 'customerID',
            'message_body' => 'messageBody',
            'phone_international' => 'phoneInternational',
            'reason_code' => 'reasonCode',
            'region_code' => 'regionCode',
            'template_category' => 'templateCategory',
            'template_id' => 'templateID',
            'template_name' => 'templateName',
        ];

        foreach ($fields as $wireName => $propertyName) {
            if (! array_key_exists($wireName, $data)) {
                continue;
            }

            $data[$propertyName] = $data[$wireName];
            unset($data[$wireName]);
        }

        return $data;
    }
}
