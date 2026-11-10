<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Core\FileParam;
use SentDm\SenderProfiles\APIResponseOfSenderProfile;
use SentDm\SenderProfiles\SenderProfile;
use SentDm\SenderProfilesPage;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Concerns\Paginatable;
use Sujip\SentDm\Responses\SenderProfileData;

/**
 * `/v3/sender-profiles`. Reads and delete use the typed SDK calls. Create and update still
 * go through `Resource::raw()`.
 *
 * This is the resource Sent.dm's August 2026 platform changelog names as the replacement
 * for the deprecated `profiles` service. Unlike `Profiles`, ownership of each capability
 * (billing, channels) is expressed by presence, not a flag: send `{"inherit": true}` to
 * use the organization's, supply the capability's own fields to give the profile its own,
 * or omit the block entirely for neither.
 *
 * @phpstan-type SenderProfileShape = array{
 *   name: string,
 *   short_name?: string|null,
 *   description?: string|null,
 *   billing?: array<string, mixed>|null,
 *   channels?: array<string, mixed>|null,
 *   compliance?: array<string, mixed>|null,
 *   sandbox?: bool|null,
 * }
 */
class SenderProfiles extends Resource
{
    use Paginatable;

    /** @return SenderProfilesPage<SenderProfile> */
    public function get(): SenderProfilesPage
    {
        return $this->client->senderProfiles->list(
            page: $this->page,
            pageSize: $this->pageSize,
            requestOptions: $this->profileOptions(),
        );
    }

    public function find(string $id): APIResponseOfSenderProfile
    {
        return $this->cached(
            "sent.sender-profile.{$id}",
            fn () => $this->client->senderProfiles->retrieve($id, requestOptions: $this->profileOptions()),
        );
    }

    public function create(): SenderProfileBuilder
    {
        return new SenderProfileBuilder($this, mode: 'create', sandboxDefault: $this->sandbox);
    }

    public function update(string $id): SenderProfileBuilder
    {
        return new SenderProfileBuilder(
            $this,
            mode: 'update',
            id: $id,
            onSaved: fn () => $this->forget("sent.sender-profile.{$id}"),
            sandboxDefault: $this->sandbox,
        );
    }

    public function delete(string $id): void
    {
        $this->client->senderProfiles->delete($id, requestOptions: $this->profileOptions());
        $this->forget("sent.sender-profile.{$id}");
    }

    /**
     * @internal used by SenderProfileBuilder::save()
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(string $method, string $path, array $data, ?string $idempotencyKey = null): SenderProfileData
    {
        return SenderProfileData::fromArray($this->raw($method, $path, body: $data, headers: $this->idempotencyHeader($idempotencyKey)));
    }

    /**
     * @internal used by SenderProfileBuilder::save() when attach() was called
     *
     * Document upload: the JSON body goes unchanged into a `profile` field, each file
     * under the field name its compliance key names, e.g. `business_registration`.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, FileParam>  $attachments
     */
    public function submitMultipart(array $data, array $attachments, ?string $idempotencyKey = null): SenderProfileData
    {
        $body = ['profile' => json_encode($data, JSON_THROW_ON_ERROR)] + $attachments;
        $headers = ['Content-Type' => 'multipart/form-data'] + $this->idempotencyHeader($idempotencyKey);

        return SenderProfileData::fromArray($this->raw('post', 'v3/sender-profiles', body: $body, headers: $headers));
    }
}
