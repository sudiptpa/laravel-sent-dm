<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use SentDm\Client;
use SentDm\Me\MeGetResponse;
use SentDm\Numbers\NumberLookupResponse;
use Sujip\SentDm\Contracts\ResolvesOptOutScope;
use Sujip\SentDm\Contracts\SentDriverInterface;
use Sujip\SentDm\Exceptions\ContactOptedOutException;
use Sujip\SentDm\Jobs\SendBulkMessages;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Models\SentOptOut;
use Sujip\SentDm\Resources\Account;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Resources\Compliance;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Conversations;
use Sujip\SentDm\Resources\Messages;
use Sujip\SentDm\Resources\Numbers;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\SenderProfiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Support\Sandbox;

class Sent implements SentDriverInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly ?CacheRepository $cache = null,
        private readonly bool $cacheEnabled = false,
        private readonly int $cacheTtl = 3600,
        private readonly bool $sandbox = false,
        private readonly string $connectionName = 'default',
        private readonly bool $optOutGuard = false,
        private readonly ?string $defaultChannel = null,
        private readonly ?ResolvesOptOutScope $optOutScopeResolver = null,
    ) {}

    // Messaging ----------------------------------------------------------------

    public function to(string $recipient): SentMessage
    {
        return SentMessage::create()
            ->withManager($this)
            ->to($recipient);
    }

    /** @param array<int, string> $recipients */
    public function bulk(array $recipients): SentBulkDispatcher
    {
        return new SentBulkDispatcher($this, $recipients, $this->connectionName);
    }

    /** @param array<int, string> $recipients */
    public function dispatchBulk(array $recipients, SentMessage $template, ?string $connection): void
    {
        SendBulkMessages::dispatch($recipients, $template, $connection);
    }

    public function send(SentMessage $message): mixed
    {
        $recipient = $message->getRecipient();

        if ($recipient === null || $recipient === '') {
            throw new InvalidArgumentException('SentMessage must have a recipient before calling send().');
        }

        $this->assertNotOptedOut($recipient, $message);

        $template = null;

        if (($name = $message->getTemplateName()) !== null) {
            $template = ['name' => $name];

            if (($id = $message->getTemplateId()) !== null) {
                $template['id'] = $id;
            }

            if ($data = $message->getTemplateData()) {
                $template['parameters'] = $data;
            }
        }

        $channels = $message->getChannels();
        if ($channels === [] && $this->defaultChannel !== null) {
            $channels = [$this->defaultChannel];
        }

        if ($message->getMediaUrls() !== [] || $message->getScheduledAt() !== null || $message->getSubject() !== null) {
            $body = array_filter([
                'channel' => $channels !== [] ? $channels : null,
                'media_urls' => $message->getMediaUrls() !== [] ? $message->getMediaUrls() : null,
                'sandbox' => Sandbox::resolve($message->getSandbox(), $this->sandbox),
                'scheduled_at' => $message->getScheduledAt(),
                'subject' => $message->getSubject(),
                'template' => $template,
                'text' => $template === null ? $message->getContent() : null,
                'to' => [$recipient],
            ], fn (mixed $value): bool => $value !== null);

            $headers = [];
            if ($message->getIdempotencyKey() !== null) {
                $headers['Idempotency-Key'] = $message->getIdempotencyKey();
            }
            if ($message->getProfileId() !== null) {
                $headers['x-profile-id'] = $message->getProfileId();
            }

            $data = $this->client->request(
                method: 'post',
                path: 'v3/messages',
                headers: $headers,
                body: $body,
                unwrap: 'data',
                convert: 'mixed',
            )->parse() ?? [];

            /** @var array<string, mixed> $data */
            return Messages::sendResponseFromRawData($data);
        }

        return $this->client->messages->send(
            channel: $channels !== [] ? $channels : null,
            idempotencyKey: $message->getIdempotencyKey(),
            sandbox: ($message->getSandbox() ?? $this->sandbox) ?: null,
            template: $template,
            text: $template === null ? $message->getContent() : null,
            to: [$recipient],
            xProfileID: $message->getProfileId(),
        );
    }

    public function dispatch(SentMessage $message): void
    {
        // The opt-out guard is intentionally not checked here. send() is always
        // called inside the queued job, which catches ContactOptedOutException
        // and calls fail(). This preserves the "sendLater never blocks the
        // request cycle" contract. Consumers who want to skip queueing
        // altogether should call $user->optedOutFromSent() before dispatching.
        SendSentMessage::dispatch($message->withoutManager(), $this->connectionName);
    }

    // Account ------------------------------------------------------------------

    public function account(): MeGetResponse
    {
        return $this->client->me->retrieve();
    }

    /**
     * Chainable entry point for me.retrieve, e.g. Sent::me()->profile($id)->get().
     * account() above is the shortcut for the common case of no org-profile scoping.
     */
    public function me(): Account
    {
        return new Account($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, connectionName: $this->connectionName);
    }

    // Number lookup ------------------------------------------------------------

    public function lookup(string $phoneNumber): NumberLookupResponse
    {
        return $this->numbers()->lookup($phoneNumber);
    }

    /**
     * Chainable entry point for numbers.lookup, e.g. Sent::numbers()->profile($id)->lookup(...).
     * lookup() above is the shortcut for the common case of no org-profile scoping.
     */
    public function numbers(): Numbers
    {
        return new Numbers($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, connectionName: $this->connectionName);
    }

    // Resource factories -------------------------------------------------------

    public function messages(): Messages
    {
        return new Messages($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    public function contacts(): Contacts
    {
        return new Contacts($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    public function conversations(): Conversations
    {
        return new Conversations($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, connectionName: $this->connectionName);
    }

    public function templates(): Templates
    {
        return new Templates($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    public function webhooks(): Webhooks
    {
        return new Webhooks($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    /**
     * @deprecated Sent.dm deprecated the entire `profiles` service in its August 2026
     * platform changelog, in favor of the new `sender-profiles` resource. Still fully
     * functional. Use senderProfiles() for new integrations.
     */
    public function profiles(): Profiles
    {
        return new Profiles($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    /**
     * `/v3/sender-profiles`, Sent.dm's replacement for the deprecated `profiles` service.
     * Not in any published SDK version yet; calls the SDK client's request method.
     */
    public function senderProfiles(): SenderProfiles
    {
        return new SenderProfiles($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    /**
     * `/v3/channels`. Not in any published SDK version yet; calls the SDK client's
     * request method.
     */
    public function channels(): Channels
    {
        return new Channels($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    /**
     * `/v3/compliance/requirements`. Not in any published SDK version yet; calls the
     * SDK client's request method.
     */
    public function compliance(): Compliance
    {
        return new Compliance($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, connectionName: $this->connectionName);
    }

    public function users(): Users
    {
        return new Users($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);
    }

    private function assertNotOptedOut(string $recipient, SentMessage $message): void
    {
        if (! $this->optOutGuard) {
            return;
        }

        $scope = $this->optOutScopeResolver?->forMessage($message, $this->connectionName);

        if (SentOptOut::isOptedOut($recipient, $scope)) {
            throw new ContactOptedOutException($recipient);
        }
    }
}
