<?php

declare(strict_types=1);

namespace Sujip\SentDm\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SentDm\Core\Exceptions\RateLimitException;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Events\MessageSent;
use Sujip\SentDm\Exceptions\ContactOptedOutException;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\SentManager;

class SendSentMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly SentMessage $message,
        private readonly ?string $sentConnection = null,
    ) {
        $queueConnection = config('sent.queue.connection');
        $name = config('sent.queue.name', 'default');

        $this->onConnection(is_string($queueConnection) ? $queueConnection : null);
        $this->onQueue(is_string($name) ? $name : 'default');
        $this->afterCommit();
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(SentManager $manager): void
    {
        try {
            $response = $manager->connection($this->sentConnection)->send($this->message);
            event(new MessageSent($this->message, $response, connectionName: $this->sentConnection));
        } catch (ContactOptedOutException $e) {
            $this->fail($e);
        } catch (RateLimitException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->fail($e);

                return;
            }

            $retryAfter = $e->response?->getHeaderLine('Retry-After') ?? '';
            $this->release(is_numeric($retryAfter) ? (int) $retryAfter : 60);
        }
    }

    public function failed(\Throwable $exception): void
    {
        event(new MessageFailed($this->message, $exception, connectionName: $this->sentConnection));
    }
}
