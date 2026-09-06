<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Core\FileParam;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Responses\SenderProfileData;
use Sujip\SentDm\Responses\SenderProfileListData;

/**
 * `/v3/sender-profiles`. Not in any published `sentdm/sent-dm-php` version yet, so this
 * calls the SDK's own generic `Client::request()` (via `Resource::raw()`) instead of a
 * typed convenience method (see `CONTRIBUTING.md`). Field shapes come from Sent.dm's
 * published OpenAPI spec (api.sent.dm/swagger/v3/swagger.json).
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
    private int $page = 1;

    private int $pageSize = 50;

    public function page(int $page): static
    {
        $clone = clone $this;
        $clone->page = $page;

        return $clone;
    }

    public function perPage(int $perPage): static
    {
        $clone = clone $this;
        $clone->pageSize = $perPage;

        return $clone;
    }

    public function get(): SenderProfileListData
    {
        return SenderProfileListData::fromArray($this->raw('get', 'v3/sender-profiles', query: [
            'page' => $this->page,
            'page_size' => $this->pageSize,
        ]));
    }

    public function find(string $id): SenderProfileData
    {
        return $this->cached(
            "sent.sender-profile.{$id}",
            fn () => SenderProfileData::fromArray($this->raw('get', "v3/sender-profiles/{$id}")),
        );
    }

    public function create(): SenderProfileBuilder
    {
        return new SenderProfileBuilder($this, mode: 'create', sandboxDefault: $this->sandbox);
    }

    public function update(string $id): SenderProfileBuilder
    {
        return new SenderProfileBuilder($this, mode: 'update', id: $id, sandboxDefault: $this->sandbox);
    }

    /**
     * Same fix as Webhooks::delete(): an empty body fails live, a non-empty one works.
     * The key is filler, not a real field, the server ignores it either way.
     */
    public function delete(string $id): void
    {
        $this->raw('delete', "v3/sender-profiles/{$id}", body: ['_' => true]);
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
