<?php

declare(strict_types=1);

namespace Sujip\SentDm\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sujip\SentDm\Events\MessageFailed;
use Sujip\SentDm\Messages\SentMessage;

class SendBulkMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @param array<int, string> $recipients */
    public function __construct(
        private readonly array $recipients,
        private readonly SentMessage $template,
        private readonly ?string $sentConnection = null,
    ) {
        $queueConnection = config('sent.queue.connection');
        $name = config('sent.queue.name', 'default');

        $this->onConnection(is_string($queueConnection) ? $queueConnection : null);
        $this->onQueue(is_string($name) ? $name : 'default');
        $this->afterCommit();
    }

    public function handle(Dispatcher $bus): void
    {
        $jobs = array_map(
            fn (string $recipient) => new SendSentMessage($this->template->to($recipient), $this->sentConnection),
            $this->recipients,
        );

        $bus->batch($jobs)
            ->allowFailures()
            ->dispatch();
    }

    public function failed(\Throwable $exception): void
    {
        event(new MessageFailed(null, $exception));
    }
}
