<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use SentDm\Client;
use SentDm\Contacts\APIResponseOfContact;
use SentDm\Contacts\ContactListResponse;
use SentDm\Me\MeGetResponse;
use SentDm\Numbers\NumberLookupResponse;
use SentDm\Profiles\APIResponseOfProfileDetail;
use SentDm\Profiles\ProfileListResponse;
use SentDm\Templates\APIResponseTemplate;
use SentDm\Templates\Template;
use SentDm\Templates\TemplateListResponse;
use SentDm\Users\APIResponseOfUser;
use SentDm\Users\UserListResponse;
use SentDm\Webhooks\APIResponseWebhook;
use SentDm\Webhooks\WebhookListResponse;
use Sujip\SentDm\Contracts\SentDriverInterface;
use Sujip\SentDm\Jobs\SendSentMessage;
use Sujip\SentDm\Messages\SentMessage;

class Sent implements SentDriverInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly ?CacheRepository $cache = null,
        private readonly bool $cacheEnabled = false,
        private readonly int $cacheTtl = 3600,
        private readonly bool $sandbox = false,
        private readonly string $connectionName = 'default',
    ) {}

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
            to: [$recipient],
            xProfileID: $message->getProfileId(),
        );
    }

    public function dispatch(SentMessage $message): void
    {
        SendSentMessage::dispatch($message->withoutManager(), $this->connectionName);
    }

    public function account(): MeGetResponse
    {
        return $this->client->me->retrieve();
    }

    public function listTemplates(int $page = 1, int $pageSize = 50): TemplateListResponse
    {
        return $this->cached("sent.templates.{$page}.{$pageSize}", fn () => $this->client->templates->list(page: $page, pageSize: $pageSize));
    }

    public function lookup(string $phoneNumber): NumberLookupResponse
    {
        $key = 'sent.lookup.'.ltrim($phoneNumber, '+');

        return $this->cached($key, fn () => $this->client->numbers->lookup(phoneNumber: $phoneNumber));
    }

    /** @param list<string> $eventTypes */
    public function createWebhook(string $endpointUrl, array $eventTypes): APIResponseWebhook
    {
        return $this->client->webhooks->create(
            endpointURL: $endpointUrl,
            eventTypes: $eventTypes,
        );
    }
    // Contacts -----------------------------------------------------------------

    public function createContact(string $phoneNumber): APIResponseOfContact
    {
        return $this->client->contacts->create(phoneNumber: $phoneNumber);
    }

    public function listContacts(
        int $page = 1,
        int $pageSize = 50,
        ?string $search = null,
        ?string $channel = null,
    ): ContactListResponse {
        return $this->client->contacts->list(
            page: $page,
            pageSize: $pageSize,
            search: $search,
            channel: $channel,
        );
    }

    public function getContact(string $id): APIResponseOfContact
    {
        return $this->cached("sent.contact.{$id}", fn () => $this->client->contacts->retrieve(id: $id));
    }

    public function updateContact(
        string $id,
        ?string $defaultChannel = null,
        ?bool $optOut = null,
    ): APIResponseOfContact {
        $result = $this->client->contacts->update(id: $id, defaultChannel: $defaultChannel, optOut: $optOut);
        $this->forget("sent.contact.{$id}");

        return $result;
    }

    public function deleteContact(string $id): void
    {
        $this->client->contacts->delete(id: $id, body: []);
        $this->forget("sent.contact.{$id}");
    }

    // Templates ----------------------------------------------------------------

    public function getTemplate(string $id): APIResponseTemplate
    {
        return $this->cached("sent.template.{$id}", fn () => $this->client->templates->retrieve(id: $id));
    }

    public function getTemplateByName(string $name): ?Template
    {
        return $this->cached("sent.template.name.{$name}", function () use ($name): ?Template {
            $response = $this->client->templates->list(page: 1, pageSize: 100, search: $name);

            foreach ($response->data->templates ?? [] as $template) {
                if ($template->name === $name) {
                    return $template;
                }
            }

            return null;
        });
    }

    public function deleteTemplate(string $id): void
    {
        $this->client->templates->delete(id: $id);
        $this->forget("sent.template.{$id}");
    }

    // Profiles -----------------------------------------------------------------

    public function listProfiles(): ProfileListResponse
    {
        return $this->cached('sent.profiles.all', fn () => $this->client->profiles->list());
    }

    public function getProfile(string $profileId): APIResponseOfProfileDetail
    {
        return $this->client->profiles->retrieve(profileID: $profileId);
    }

    public function deleteProfile(string $profileId): void
    {
        $this->client->profiles->delete(profileID: $profileId, body: []);
        $this->forget('sent.profiles.all');
    }

    // Users --------------------------------------------------------------------

    public function listUsers(): UserListResponse
    {
        return $this->client->users->list();
    }

    public function getUser(string $userId): APIResponseOfUser
    {
        return $this->client->users->retrieve(userID: $userId);
    }

    public function inviteUser(string $email, string $name, string $role): APIResponseOfUser
    {
        return $this->client->users->invite(email: $email, name: $name, role: $role);
    }

    public function removeUser(string $userId): void
    {
        $this->client->users->remove(userID: $userId, body: []);
    }

    // Webhooks -----------------------------------------------------------------

    public function listWebhooks(int $page = 1, int $pageSize = 50): WebhookListResponse
    {
        return $this->client->webhooks->list(page: $page, pageSize: $pageSize);
    }

    public function getWebhook(string $id): APIResponseWebhook
    {
        return $this->client->webhooks->retrieve(id: $id);
    }

    public function deleteWebhook(string $id): void
    {
        $this->client->webhooks->delete(id: $id);
    }

    public function toggleWebhook(string $id, bool $active): APIResponseWebhook
    {
        return $this->client->webhooks->toggleStatus(id: $id, isActive: $active);
    }

    public function rotateWebhookSecret(string $id): mixed
    {
        return $this->client->webhooks->rotateSecret(id: $id, body: []);
    }
    // Cache helpers -----------------------------------------------------------

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function cached(string $key, Closure $callback): mixed
    {
        if (! $this->cacheEnabled || $this->cache === null) {
            return $callback();
        }

        return $this->cacheStore()->remember($key, $this->cacheTtl, $callback);
    }

    private function forget(string $key): void
    {
        if ($this->cacheEnabled && $this->cache !== null) {
            $this->cacheStore()->forget($key);
        }
    }

    private function cacheStore(): CacheRepository
    {
        if ($this->cache !== null && $this->cache->getStore() instanceof TaggableStore) {
            return $this->cache->tags(['sent']);
        }

        return $this->cache ?? app('cache.store');
    }
}
