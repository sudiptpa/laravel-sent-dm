<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Channels\SMS\MarketCompliance;
use SentDm\Core\Conversion;
use SentDm\Core\FileParam;
use SentDm\SenderProfiles\APIResponseOfSenderProfile;
use SentDm\SenderProfiles\SenderProfile;
use SentDm\SenderProfiles\SenderProfileBillingRequest;
use SentDm\SenderProfiles\SenderProfileChannels;
use SentDm\SenderProfiles\SenderProfileCreateParams;
use SentDm\SenderProfilesPage;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Concerns\Paginatable;

/**
 * `/v3/sender-profiles`.
 *
 * This is the resource Sent.dm's August 2026 platform changelog names as the replacement
 * for the deprecated `profiles` service. Unlike `Profiles`, ownership of each capability
 * (billing, channels) is expressed by presence, not a flag: send `{"inherit": true}` to
 * use the organization's, supply the capability's own fields to give the profile its own,
 * or omit the block entirely for neither.
 *
 * `profile()` has no effect here. The API rejects `x-profile-id` on this route, since this is
 * where an organization manages its profiles.
 *
 * @phpstan-import-type MarketComplianceShape from MarketCompliance
 * @phpstan-import-type SenderProfileBillingRequestShape from SenderProfileBillingRequest
 * @phpstan-import-type SenderProfileChannelsShape from SenderProfileChannels
 *
 * @phpstan-type SenderProfileShape = array{
 *   name: string,
 *   shortName?: string|null,
 *   description?: string|null,
 *   billing?: SenderProfileBillingRequestShape|null,
 *   channels?: SenderProfileChannelsShape|null,
 *   compliance?: MarketComplianceShape|null,
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
        );
    }

    public function find(string $id): APIResponseOfSenderProfile
    {
        return $this->cached(
            "sent.sender-profile.{$id}",
            fn () => $this->client->senderProfiles->retrieve($id),
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
        $this->client->senderProfiles->delete($id);
        $this->forget("sent.sender-profile.{$id}");
    }

    /**
     * @internal used by SenderProfileBuilder::save()
     *
     * @param  array{billing?: SenderProfileBillingRequestShape|null, channels?: SenderProfileChannelsShape|null, compliance?: MarketComplianceShape|null, description?: string|null, name?: string|null, notifications?: string|null, sandbox?: bool|null, shortName?: string|null}  $data
     */
    public function submitCreate(array $data, ?string $idempotencyKey = null): APIResponseOfSenderProfile
    {
        return $this->client->senderProfiles->create(
            billing: $data['billing'] ?? null,
            channels: $data['channels'] ?? null,
            compliance: $data['compliance'] ?? null,
            description: $data['description'] ?? null,
            name: $data['name'] ?? null,
            notifications: $data['notifications'] ?? null,
            sandbox: $data['sandbox'] ?? null,
            shortName: $data['shortName'] ?? null,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @internal used by SenderProfileBuilder::save()
     *
     * @param  array{description?: string|null, name?: string|null, notifications?: string|null, sandbox?: bool|null, shortName?: string|null}  $data
     */
    public function submitUpdate(string $id, array $data, ?string $idempotencyKey = null): APIResponseOfSenderProfile
    {
        return $this->client->senderProfiles->update(
            $id,
            description: $data['description'] ?? null,
            name: $data['name'] ?? null,
            notifications: $data['notifications'] ?? null,
            sandbox: $data['sandbox'] ?? null,
            shortName: $data['shortName'] ?? null,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @internal used by SenderProfileBuilder::save() when attach() was called
     *
     * The typed call cannot send files: the JSON goes in a `profile` field, each file under its key.
     *
     * @param  array{billing?: SenderProfileBillingRequestShape|null, channels?: SenderProfileChannelsShape|null, compliance?: MarketComplianceShape|null, description?: string|null, name?: string|null, notifications?: string|null, sandbox?: bool|null, shortName?: string|null}  $data
     * @param  array<string, FileParam>  $attachments
     */
    public function submitMultipart(array $data, array $attachments, ?string $idempotencyKey = null): APIResponseOfSenderProfile
    {
        $body = ['profile' => json_encode($this->wireBody($data), JSON_THROW_ON_ERROR)] + $attachments;
        $headers = ['Content-Type' => 'multipart/form-data'] + $this->idempotencyHeader($idempotencyKey);

        return $this->rawModel(APIResponseOfSenderProfile::class, 'post', 'v3/sender-profiles', body: $body, headers: $headers);
    }

    /**
     * Same key conversion as the typed call.
     *
     * @param  array{billing?: SenderProfileBillingRequestShape|null, channels?: SenderProfileChannelsShape|null, compliance?: MarketComplianceShape|null, description?: string|null, name?: string|null, notifications?: string|null, sandbox?: bool|null, shortName?: string|null}  $data
     * @return array<string, mixed>
     */
    private function wireBody(array $data): array
    {
        $params = SenderProfileCreateParams::with(
            billing: $data['billing'] ?? null,
            channels: $data['channels'] ?? null,
            compliance: $data['compliance'] ?? null,
            description: $data['description'] ?? null,
            name: $data['name'] ?? null,
            notifications: $data['notifications'] ?? null,
            sandbox: $data['sandbox'] ?? null,
            shortName: $data['shortName'] ?? null,
        );

        /** @var array<string, mixed> */
        return Conversion::dump(SenderProfileCreateParams::class, $params->toProperties());
    }
}
