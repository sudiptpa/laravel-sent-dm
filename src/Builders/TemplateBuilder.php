<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Closure;
use SentDm\Client;
use SentDm\Templates\TemplateDefinition;
use SentDm\Templates\TemplateNewResponse;
use SentDm\Templates\TemplateUpdateResponse;
use Sujip\SentDm\Concerns\HasIdempotencyKey;
use Sujip\SentDm\Concerns\HasSandbox;

/**
 * @phpstan-import-type TemplateDefinitionShape from TemplateDefinition
 */
class TemplateBuilder
{
    use HasIdempotencyKey, HasSandbox;

    private ?string $name = null;

    private ?string $category = null;

    private ?string $language = null;

    /** @var TemplateDefinition|TemplateDefinitionShape|null */
    private TemplateDefinition|array|null $definition = null;

    private ?bool $submitForReview = null;

    private ?string $creationSource = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
        private readonly ?string $profileId = null,
        private readonly ?Closure $onSaved = null,
        private readonly bool $sandboxDefault = false,
    ) {}

    public function name(string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function category(string $category): static
    {
        $clone = clone $this;
        $clone->category = $category;

        return $clone;
    }

    public function language(string $language): static
    {
        $clone = clone $this;
        $clone->language = $language;

        return $clone;
    }

    /**
     * @param  TemplateDefinition|TemplateDefinitionShape  $definition
     */
    public function definition(TemplateDefinition|array $definition): static
    {
        $clone = clone $this;
        $clone->definition = $definition;

        return $clone;
    }

    public function submitForReview(bool $submitForReview = true): static
    {
        $clone = clone $this;
        $clone->submitForReview = $submitForReview;

        return $clone;
    }

    /** Create-only, like name() is update-only. Defaults to `from-api` server-side. */
    public function creationSource(string $creationSource): static
    {
        $clone = clone $this;
        $clone->creationSource = $creationSource;

        return $clone;
    }

    public function save(): TemplateNewResponse|TemplateUpdateResponse
    {
        $sandbox = ($this->sandbox ?? $this->sandboxDefault) ?: null;

        if ($this->id !== null) {
            if ($this->creationSource !== null) {
                throw new \InvalidArgumentException(
                    'creationSource() is not supported when updating a template. The Sent.dm API only accepts it on create.'
                );
            }

            $result = $this->client->templates->update(
                id: $this->id,
                category: $this->category,
                definition: $this->definition,
                language: $this->language,
                name: $this->name,
                submitForReview: $this->submitForReview,
                sandbox: $sandbox,
                idempotencyKey: $this->idempotencyKey,
                xProfileID: $this->profileId,
            );

            if ($this->onSaved !== null) {
                ($this->onSaved)();
            }

            return $result;
        }

        if ($this->name !== null) {
            throw new \InvalidArgumentException(
                'name() is not supported when creating a template. The Sent.dm API only accepts name on update. Create the template first, then use templates()->update() to set the name.'
            );
        }

        return $this->client->templates->create(
            category: $this->category,
            definition: $this->definition,
            language: $this->language,
            submitForReview: $this->submitForReview,
            creationSource: $this->creationSource,
            sandbox: $sandbox,
            idempotencyKey: $this->idempotencyKey,
            xProfileID: $this->profileId,
        );
    }
}
