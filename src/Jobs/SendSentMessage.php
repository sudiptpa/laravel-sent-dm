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
            event(new MessageSent($this->message, $response));
        } catch (RateLimitException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->fail($e);

                return;
            }

            // Use the Retry-After header the API returns; fall back to 60 s.
            $retryAfter = $e->response?->getHeaderLine('Retry-After') ?? '';
            $this->release(is_numeric($retryAfter) ? (int) $retryAfter : 60);
        }
    }

    public function failed(\Throwable $exception): void
    {
        event(new MessageFailed($this->message, $exception));
    }
}
