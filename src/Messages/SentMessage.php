<?php

declare(strict_types=1);

namespace Sujip\SentDm\Messages;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;
use Sujip\SentDm\Contracts\SentDriverInterface;

final class SentMessage
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $recipient = null;

    private ?string $content = null;

    /** @var list<string> */
    private array $channels = [];

    private ?string $templateName = null;

    private ?string $templateId = null;

    /** @var array<string, string> */
    private array $templateData = [];

    private ?string $profileId = null;

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

    /**
     * One channel, or several to fan out on: each channel produces a separate,
     * separately-tracked message to the same recipient. "sms" and "whatsapp" send on
     * both if configured; "sent" (the default when this is never called) auto-detects.
     *
     * @param  string|list<string>  $channel
     */
    public function channel(string|array $channel): static
    {
        $clone = clone $this;
        $clone->channels = is_array($channel) ? $channel : [$channel];

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

    /** First channel set, or null if none. Kept for callers that only ever set one. */
    public function getChannel(): ?string
    {
        return $this->channels[0] ?? null;
    }

    /** @return list<string> */
    public function getChannels(): array
    {
        return $this->channels;
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
     *     channels: list<string>,
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
            'channels' => $this->channels,
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
     *     channels?: list<string>,
     *     channel?: string|null,
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
        // Falls back to the old single-`channel` shape for a job already queued (and not
        // yet run) from before this version, so a mid-deploy queue doesn't lose it.
        $this->channels = $data['channels'] ?? (($data['channel'] ?? null) !== null ? [$data['channel']] : []);
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
