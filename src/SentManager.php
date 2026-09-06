<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use SentDm\Client;
use SentDm\Me\MeGetResponse;
use SentDm\Numbers\NumberLookupResponse;
use Sujip\SentDm\Messages\SentMessage;
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

/**
 * Multi-tenant driver manager, same pattern as Laravel Mail/Cache.
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
        $optOutGuard = (bool) $this->config->get('sent.opt_out.guard', false);

        return new Sent(
            client: new Client(apiKey: $apiKey),
            cache: $cache,
            cacheEnabled: $cacheEnabled,
            cacheTtl: $cacheTtl,
            sandbox: $sandbox,
            connectionName: (string) $driver,
            optOutGuard: $optOutGuard,
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

    // Proxy convenience methods: resolve the default connection implicitly.

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

    public function lookup(string $phoneNumber): NumberLookupResponse
    {
        return $this->connection()->lookup($phoneNumber);
    }

    public function numbers(): Numbers
    {
        return $this->connection()->numbers();
    }

    // Resource proxies ---------------------------------------------------------

    public function messages(): Messages
    {
        return $this->connection()->messages();
    }

    public function contacts(): Contacts
    {
        return $this->connection()->contacts();
    }

    public function conversations(): Conversations
    {
        return $this->connection()->conversations();
    }

    public function templates(): Templates
    {
        return $this->connection()->templates();
    }

    public function webhooks(): Webhooks
    {
        return $this->connection()->webhooks();
    }

    /**
     * @deprecated Sent.dm deprecated the entire `profiles` service in its August 2026
     * platform changelog, in favor of the new `sender-profiles` resource. Still fully
     * functional; no replacement exists in the SDK yet, so nothing to migrate to.
     */
    public function profiles(): Profiles
    {
        return $this->connection()->profiles();
    }

    public function senderProfiles(): SenderProfiles
    {
        return $this->connection()->senderProfiles();
    }

    public function channels(): Channels
    {
        return $this->connection()->channels();
    }

    public function compliance(): Compliance
    {
        return $this->connection()->compliance();
    }

    public function users(): Users
    {
        return $this->connection()->users();
    }
}
