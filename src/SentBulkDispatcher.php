<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use InvalidArgumentException;
use Sujip\SentDm\Jobs\SendBulkMessages;
use Sujip\SentDm\Messages\SentMessage;

class SentBulkDispatcher
{
    private SentMessage $template;

    /** @param array<int, string> $recipients */
    public function __construct(
        private readonly array $recipients,
        private readonly ?string $connection = null,
    ) {
        $this->template = SentMessage::create();
    }

    public function message(string $content): static
    {
        $clone = clone $this;
        $clone->template = $this->template->message($content);

        return $clone;
    }

    public function template(string $name, ?string $id = null): static
    {
        $clone = clone $this;
        $clone->template = $this->template->template($name, $id);

        return $clone;
    }

    /** @param array<string, string> $data */
    public function with(array $data): static
    {
        $clone = clone $this;
        $clone->template = $this->template->with($data);

        return $clone;
    }

    /** @param  string|list<string>  $channel */
    public function channel(string|array $channel): static
    {
        $clone = clone $this;
        $clone->template = $this->template->channel($channel);

        return $clone;
    }

    public function usingProfile(string $profileId): static
    {
        $clone = clone $this;
        $clone->template = $this->template->usingProfile($profileId);

        return $clone;
    }

    public function dispatch(): void
    {
        if (empty($this->recipients)) {
            throw new InvalidArgumentException('SentBulkDispatcher requires at least one recipient.');
        }

        SendBulkMessages::dispatch($this->recipients, $this->template, $this->connection);
    }
}
