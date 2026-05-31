<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Manager;
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
use Sujip\SentDm\Messages\SentMessage;

/**
 * Multi-tenant driver manager — same pattern as Laravel Mail/Cache.
 *
 * Usage:
 *   Sent::to('+61...')                          // default connection
 *   Sent::connection('tenant_a')->to('+61...')  // named connection
 */
class SentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $default = $this->config->get('sent.default', 'default');

        return is_string($default) ? $default : 'default';
    }

    protected function createDriver(mixed $driver): Sent
    {
        if (isset($this->customCreators[$driver])) {
            $instance = $this->callCustomCreator($driver);

            if (! $instance instanceof Sent) {
                throw new InvalidArgumentException(
                    "Custom Sent driver for [{$driver}] must return a ".Sent::class.' instance.'
                );
            }

            return $instance;
        }

        /** @var array<string, mixed>|null $config */
        $config = $this->config->get("sent.connections.{$driver}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Sent connection [{$driver}] is not configured.");
        }

        $apiKey = isset($config['api_key']) && is_string($config['api_key']) ? $config['api_key'] : null;
        $cacheEnabled = (bool) $this->config->get('sent.cache.enabled', true);
        $rawTtl = $this->config->get('sent.cache.ttl', 3600);
        $cacheTtl = is_numeric($rawTtl) ? (int) $rawTtl : 3600;

        /** @var CacheRepository $cache */
        $cache = $this->container->make(CacheRepository::class);

        $sandbox = (bool) $this->config->get('sent.sandbox', false);

        return new Sent(
            client: new Client(apiKey: $apiKey),
            cache: $cache,
            cacheEnabled: $cacheEnabled,
            cacheTtl: $cacheTtl,
            sandbox: $sandbox,
            connectionName: (string) $driver,
        );
    }

    /** Resolve a named connection (or the default when null). */
    public function connection(?string $name = null): Sent
    {
        $driver = $this->driver($name);

        if (! $driver instanceof Sent) {
            throw new \RuntimeException(
                'Sent driver must be an instance of '.Sent::class.'.'
            );
        }

        return $driver;
    }

    // Proxy convenience methods — resolve the default connection implicitly.

    public function to(string $recipient): SentMessage
    {
        return $this->connection()->to($recipient);
    }

    /** @param array<int, string> $recipients */
    public function bulk(array $recipients): SentBulkDispatcher
    {
        return $this->connection()->bulk($recipients);
    }

    public function send(SentMessage $message): mixed
    {
        return $this->connection()->send($message);
    }

    public function dispatch(SentMessage $message): void
    {
        $this->connection()->dispatch($message);
    }

    public function account(): MeGetResponse
    {
        return $this->connection()->account();
    }

    public function listTemplates(int $page = 1, int $pageSize = 50): TemplateListResponse
    {
        return $this->connection()->listTemplates($page, $pageSize);
    }

    public function lookup(string $phoneNumber): NumberLookupResponse
    {
        return $this->connection()->lookup($phoneNumber);
    }

    /** @param list<string> $eventTypes */
    public function createWebhook(string $endpointUrl, array $eventTypes): APIResponseWebhook
    {
        return $this->connection()->createWebhook($endpointUrl, $eventTypes);
    }
    // Contacts -----------------------------------------------------------------

    public function createContact(string $phoneNumber): APIResponseOfContact
    {
        return $this->connection()->createContact($phoneNumber);
    }

    public function listContacts(
        int $page = 1,
        int $pageSize = 50,
        ?string $search = null,
        ?string $channel = null,
    ): ContactListResponse {
        return $this->connection()->listContacts($page, $pageSize, $search, $channel);
    }

    public function getContact(string $id): APIResponseOfContact
    {
        return $this->connection()->getContact($id);
    }

    public function updateContact(
        string $id,
        ?string $defaultChannel = null,
        ?bool $optOut = null,
    ): APIResponseOfContact {
        return $this->connection()->updateContact($id, $defaultChannel, $optOut);
    }

    public function deleteContact(string $id): void
    {
        $this->connection()->deleteContact($id);
    }

    // Templates ----------------------------------------------------------------

    public function getTemplate(string $id): APIResponseTemplate
    {
        return $this->connection()->getTemplate($id);
    }

    public function getTemplateByName(string $name): ?Template
    {
        return $this->connection()->getTemplateByName($name);
    }

    public function deleteTemplate(string $id): void
    {
        $this->connection()->deleteTemplate($id);
    }

    // Profiles -----------------------------------------------------------------

    public function listProfiles(): ProfileListResponse
    {
        return $this->connection()->listProfiles();
    }

    public function getProfile(string $profileId): APIResponseOfProfileDetail
    {
        return $this->connection()->getProfile($profileId);
    }

    public function deleteProfile(string $profileId): void
    {
        $this->connection()->deleteProfile($profileId);
    }

    // Users --------------------------------------------------------------------

    public function listUsers(): UserListResponse
    {
        return $this->connection()->listUsers();
    }

    public function getUser(string $userId): APIResponseOfUser
    {
        return $this->connection()->getUser($userId);
    }

    public function inviteUser(string $email, string $name, string $role): APIResponseOfUser
    {
        return $this->connection()->inviteUser($email, $name, $role);
    }

    public function removeUser(string $userId): void
    {
        $this->connection()->removeUser($userId);
    }

    // Webhooks -----------------------------------------------------------------

    public function listWebhooks(int $page = 1, int $pageSize = 50): WebhookListResponse
    {
        return $this->connection()->listWebhooks($page, $pageSize);
    }

    public function getWebhook(string $id): APIResponseWebhook
    {
        return $this->connection()->getWebhook($id);
    }

    public function deleteWebhook(string $id): void
    {
        $this->connection()->deleteWebhook($id);
    }

    public function toggleWebhook(string $id, bool $active): APIResponseWebhook
    {
        return $this->connection()->toggleWebhook($id, $active);
    }

    public function rotateWebhookSecret(string $id): mixed
    {
        return $this->connection()->rotateWebhookSecret($id);
    }
}
