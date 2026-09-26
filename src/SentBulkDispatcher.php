<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use DateTimeInterface;
use InvalidArgumentException;
use Sujip\SentDm\Contracts\SentDriverInterface;
use Sujip\SentDm\Messages\SentMessage;

class SentBulkDispatcher
{
    private SentMessage $template;

    /** @param array<int, string> $recipients */
    public function __construct(
        private readonly SentDriverInterface $manager,
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

    /** @param  list<string>  $urls */
    public function mediaUrls(array $urls): static
    {
        $clone = clone $this;
        $clone->template = $this->template->mediaUrls($urls);

        return $clone;
    }

    public function scheduledAt(DateTimeInterface|string $scheduledAt): static
    {
        $clone = clone $this;
        $clone->template = $this->template->scheduledAt($scheduledAt);

        return $clone;
    }

    public function subject(string $subject): static
    {
        $clone = clone $this;
        $clone->template = $this->template->subject($subject);

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

        $this->manager->dispatchBulk($this->recipients, $this->template, $this->connection);
    }
}
