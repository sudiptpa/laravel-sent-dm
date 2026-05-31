<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Sujip\SentDm\Contracts\SentDriverInterface;
use Sujip\SentDm\Messages\SentMessage;

/**
 * In-memory fake for the Sent facade — records sends and queued dispatches
 * so consumer applications can assert messaging behaviour without real API calls.
 *
 * Usage in tests:
 *
 *   Sent::fake();
 *
 *   // exercise your code
 *   $user->sendWelcomeMessage();
 *
 *   Sent::assertSentTo('+61412345678');
 *   Sent::assertSentCount(1);
 *   Sent::assertNothingQueued();
 */
class SentFake implements SentDriverInterface
{
    /** @var list<SentMessage> */
    private array $sent = [];

    /** @var list<SentMessage> */
    private array $queued = [];

    /** @var list<array{SentMessage, ?string}> */
    private array $sentRecords = [];

    /** @var list<array{SentMessage, ?string}> */
    private array $queuedRecords = [];

    private ?string $pendingConnection = null;

    // -------------------------------------------------------------------------
    // Driver-level methods forwarded from the Facade / SentManager proxies
    // -------------------------------------------------------------------------

    public function to(string $recipient): SentMessage
    {
        return SentMessage::create()->withManager($this)->to($recipient);
    }

    /** @param array<int, string> $recipients */
    public function bulk(array $recipients): SentBulkDispatcher
    {
        return new SentBulkDispatcher($recipients);
    }

    public function send(SentMessage $message): null
    {
        $this->sent[] = $message;
        $this->sentRecords[] = [$message, $this->pendingConnection];
        $this->pendingConnection = null;

        return null;
    }

    public function dispatch(SentMessage $message): void
    {
        $record = $message->withoutManager();
        $this->queued[] = $record;
        $this->queuedRecords[] = [$record, $this->pendingConnection];
        $this->pendingConnection = null;
    }

    /** Sets the active connection for the next send/dispatch, then returns self. */
    public function connection(?string $name = null): static
    {
        $this->pendingConnection = $name;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Assertions — sent (synchronous)
    // -------------------------------------------------------------------------

    /**
     * Assert that at least one message was sent matching the callback.
     *
     * @param  callable(SentMessage): bool  $callback
     */
    public function assertSent(callable $callback): void
    {
        Assert::assertTrue(
            $this->filterSent($callback)->isNotEmpty(),
            'No sent message matched the given assertion.'
        );
    }

    /**
     * Assert that a message was sent to the given recipient.
     *
     * @param  (callable(SentMessage): bool)|null  $callback
     */
    public function assertSentTo(string $recipient, ?callable $callback = null): void
    {
        $this->assertSent(static function (SentMessage $message) use ($recipient, $callback): bool {
            if ($message->getRecipient() !== $recipient) {
                return false;
            }

            return $callback === null || $callback($message);
        });
    }

    /**
     * Assert that a message was sent using the given template name.
     *
     * @param  (callable(SentMessage): bool)|null  $callback
     */
    public function assertSentWithTemplate(string $name, ?callable $callback = null): void
    {
        $this->assertSent(static function (SentMessage $message) use ($name, $callback): bool {
            if ($message->getTemplateName() !== $name) {
                return false;
            }

            return $callback === null || $callback($message);
        });
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, "Expected {$count} sent message(s), got ".count($this->sent).'.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sent, 'Messages were unexpectedly sent.');
    }

    /**
     * Assert that at least one message was sent via the given connection.
     *
     * @param  (callable(SentMessage): bool)|null  $callback
     */
    public function assertSentViaConnection(string $connection, ?callable $callback = null): void
    {
        Assert::assertTrue(
            collect($this->sentRecords)
                ->filter(fn (array $r) => $r[1] === $connection && ($callback === null || $callback($r[0])))
                ->isNotEmpty(),
            "No sent message via connection '{$connection}' matched the assertion."
        );
    }

    // -------------------------------------------------------------------------
    // Assertions — queued (async via sendLater / dispatch)
    // -------------------------------------------------------------------------

    /**
     * @param  callable(SentMessage): bool  $callback
     */
    public function assertQueued(callable $callback): void
    {
        Assert::assertTrue(
            $this->filterQueued($callback)->isNotEmpty(),
            'No queued message matched the given assertion.'
        );
    }

    /**
     * @param  (callable(SentMessage): bool)|null  $callback
     */
    public function assertQueuedTo(string $recipient, ?callable $callback = null): void
    {
        $this->assertQueued(static function (SentMessage $message) use ($recipient, $callback): bool {
            if ($message->getRecipient() !== $recipient) {
                return false;
            }

            return $callback === null || $callback($message);
        });
    }

    public function assertQueuedCount(int $count): void
    {
        Assert::assertCount($count, $this->queued, "Expected {$count} queued message(s), got ".count($this->queued).'.');
    }

    public function assertNothingQueued(): void
    {
        Assert::assertEmpty($this->queued, 'Messages were unexpectedly queued.');
    }

    /**
     * Assert that at least one message was queued via the given connection.
     *
     * @param  (callable(SentMessage): bool)|null  $callback
     */
    public function assertQueuedViaConnection(string $connection, ?callable $callback = null): void
    {
        Assert::assertTrue(
            collect($this->queuedRecords)
                ->filter(fn (array $r) => $r[1] === $connection && ($callback === null || $callback($r[0])))
                ->isNotEmpty(),
            "No queued message via connection '{$connection}' matched the assertion."
        );
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /** @return list<SentMessage> */
    public function sent(): array
    {
        return $this->sent;
    }

    /** @return list<SentMessage> */
    public function queued(): array
    {
        return $this->queued;
    }

    public function hasSent(): bool
    {
        return ! empty($this->sent);
    }

    public function hasQueued(): bool
    {
        return ! empty($this->queued);
    }

    public function reset(): void
    {
        $this->sent = [];
        $this->queued = [];
        $this->sentRecords = [];
        $this->queuedRecords = [];
        $this->pendingConnection = null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  callable(SentMessage): bool  $callback
     * @return Collection<int, SentMessage>
     */
    private function filterSent(callable $callback): Collection
    {
        return collect($this->sent)->filter($callback);
    }

    /**
     * @param  callable(SentMessage): bool  $callback
     * @return Collection<int, SentMessage>
     */
    private function filterQueued(callable $callback): Collection
    {
        return collect($this->queued)->filter($callback);
    }

    /**
     * @param  array<mixed>  $arguments
     */
    public function __call(string $name, array $arguments): never
    {
        throw new \BadMethodCallException(
            "Method [{$name}] is not available on SentFake. ".
            'Sent::fake() only intercepts messaging (to/send/sendLater/bulk/dispatch). '.
            'For API-surface methods like messages(), contacts(), templates(), webhooks(), profiles(), and users(), '.
            'inject or mock the SentManager directly: $this->mock(SentManager::class, ...)'
        );
    }
}
