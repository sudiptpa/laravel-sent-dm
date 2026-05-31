<?php

declare(strict_types=1);

namespace Sujip\SentDm\Messages;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Sujip\SentDm\Contracts\SentDriverInterface;

final class SentMessage
{
    private ?string $recipient = null;

    private ?string $content = null;

    private ?string $channel = null;

    private ?string $templateName = null;

    private ?string $templateId = null;

    /** @var array<string, string> */
    private array $templateData = [];

    private ?string $profileId = null;

    private ?string $idempotencyKey = null;

    private ?bool $sandbox = null;

    private ?string $loggableType = null;

    private ?string $loggableId = null;

    private ?SentDriverInterface $manager = null;

    public static function create(): static
    {
        return new self;
    }

    /** @internal */
    public function withManager(SentDriverInterface $manager): static
    {
        $clone = clone $this;
        $clone->manager = $manager;

        return $clone;
    }

    /** @internal */
    public function withoutManager(): static
    {
        $clone = clone $this;
        $clone->manager = null;

        return $clone;
    }

    public function to(string $recipient): static
    {
        $clone = clone $this;
        $clone->recipient = $recipient;

        return $clone;
    }

    public function message(string $content): static
    {
        $clone = clone $this;
        $clone->content = $content;

        return $clone;
    }

    public function channel(string $channel): static
    {
        $clone = clone $this;
        $clone->channel = $channel;

        return $clone;
    }

    public function template(string $name, ?string $id = null): static
    {
        $clone = clone $this;
        $clone->templateName = $name;
        $clone->templateId = $id;

        return $clone;
    }

    /** @param array<string, string> $data */
    public function with(array $data): static
    {
        $clone = clone $this;
        $clone->templateData = $data;

        return $clone;
    }

    public function usingProfile(string $profileId): static
    {
        $clone = clone $this;
        $clone->profileId = $profileId;

        return $clone;
    }

    public function idempotencyKey(string $key): static
    {
        $clone = clone $this;
        $clone->idempotencyKey = $key;

        return $clone;
    }

    public function sandbox(bool $sandbox = true): static
    {
        $clone = clone $this;
        $clone->sandbox = $sandbox;

        return $clone;
    }

    public function for(Model $model): static
    {
        $clone = clone $this;
        $clone->loggableType = $model->getMorphClass();
        $key = $model->getKey();
        $clone->loggableId = is_scalar($key) ? (string) $key : null;

        return $clone;
    }

    public function getSandbox(): ?bool
    {
        return $this->sandbox;
    }

    public function getLoggableType(): ?string
    {
        return $this->loggableType;
    }

    public function getLoggableId(): ?string
    {
        return $this->loggableId;
    }

    public function send(): mixed
    {
        if ($this->manager === null) {
            throw new LogicException(
                'Call send() via the Sent facade or inject the Sent manager directly.'
            );
        }

        return $this->manager->send($this);
    }

    public function sendLater(): void
    {
        if ($this->manager === null) {
            throw new LogicException(
                'Call sendLater() via the Sent facade or inject the Sent manager directly.'
            );
        }

        $this->manager->dispatch($this);
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    /** @return array<string, string> */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function getProfileId(): ?string
    {
        return $this->profileId;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * @return array{
     *     recipient: string|null,
     *     content: string|null,
     *     channel: string|null,
     *     templateName: string|null,
     *     templateId: string|null,
     *     templateData: array<string, string>,
     *     profileId: string|null,
     *     idempotencyKey: string|null,
     *     sandbox: bool|null,
     *     loggableType: string|null,
     *     loggableId: string|null,
     * }
     */
    public function __serialize(): array
    {
        return [
            'recipient' => $this->recipient,
            'content' => $this->content,
            'channel' => $this->channel,
            'templateName' => $this->templateName,
            'templateId' => $this->templateId,
            'templateData' => $this->templateData,
            'profileId' => $this->profileId,
            'idempotencyKey' => $this->idempotencyKey,
            'sandbox' => $this->sandbox,
            'loggableType' => $this->loggableType,
            'loggableId' => $this->loggableId,
        ];
    }

    /**
     * @param array{
     *     recipient: string|null,
     *     content: string|null,
     *     channel: string|null,
     *     templateName: string|null,
     *     templateId: string|null,
     *     templateData: array<string, string>,
     *     profileId: string|null,
     *     idempotencyKey: string|null,
     *     sandbox: bool|null,
     *     loggableType: string|null,
     *     loggableId: string|null,
     * } $data
     */
    public function __unserialize(array $data): void
    {
        $this->recipient = $data['recipient'];
        $this->content = $data['content'];
        $this->channel = $data['channel'];
        $this->templateName = $data['templateName'];
        $this->templateId = $data['templateId'];
        $this->templateData = $data['templateData'];
        $this->profileId = $data['profileId'];
        $this->idempotencyKey = $data['idempotencyKey'];
        $this->sandbox = $data['sandbox'];
        $this->loggableType = $data['loggableType'];
        $this->loggableId = $data['loggableId'];
        $this->manager = null;
    }
}
