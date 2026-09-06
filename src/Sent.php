<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use SentDm\Client;
use SentDm\Me\MeGetResponse;
use SentDm\Numbers\NumberLookupResponse;
use Sujip\SentDm\Contracts\SentDriverInterface;
use Sujip\SentDm\Exceptions\ContactOptedOutException;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Models\SentOptOut;
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
        return new SentBulkDispatcher($recipients, $this->connectionName);
    }

    public function send(SentMessage $message): mixed
    {
        $recipient = $message->getRecipient();

        if ($recipient === null) {
            throw new InvalidArgumentException('SentMessage must have a recipient before calling send().');
        }

        $this->assertNotOptedOut($recipient);

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

        return $this->client->messages->send(
            channel: $message->getChannel() !== null ? [$message->getChannel()] : null,
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
        return new Numbers($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    // Resource factories -------------------------------------------------------

    public function messages(): Messages
    {
        return new Messages($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    public function contacts(): Contacts
    {
        return new Contacts($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    public function conversations(): Conversations
    {
        return new Conversations($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    public function templates(): Templates
    {
        return new Templates($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    public function webhooks(): Webhooks
    {
        return new Webhooks($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox);
    }

    /**
     * @deprecated Sent.dm deprecated the entire `profiles` service in its August 2026
     * platform changelog, in favor of the new `sender-profiles` resource. Still fully
     * functional; no replacement exists in the SDK yet, so nothing to migrate to.
     */
    public function profiles(): Profiles
    {
        return new Profiles($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    /**
     * `/v3/sender-profiles`, Sent.dm's replacement for the deprecated `profiles` service.
     * Not in any published SDK version yet; calls the SDK's generic `request()` method
     * directly.
     */
    public function senderProfiles(): SenderProfiles
    {
        return new SenderProfiles($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox);
    }

    /**
     * `/v3/channels`. Not in any published SDK version yet; calls the SDK's generic
     * `request()` method directly.
     */
    public function channels(): Channels
    {
        return new Channels($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox);
    }

    /**
     * `/v3/compliance/requirements`. Not in any published SDK version yet; calls the
     * SDK's generic `request()` method directly.
     */
    public function compliance(): Compliance
    {
        return new Compliance($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    public function users(): Users
    {
        return new Users($this->client, $this->cache, $this->cacheEnabled, $this->cacheTtl);
    }

    private function assertNotOptedOut(string $recipient): void
    {
        if ($this->optOutGuard && $recipient !== '') {
            if (SentOptOut::where('phone_number', $recipient)->where('opted_out', true)->exists()) {
                throw new ContactOptedOutException($recipient);
            }
        }
    }
}
