<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Closure;
use SentDm\Client;
use SentDm\Templates\APIResponseTemplate;
use SentDm\Templates\TemplateDefinition;

/**
 * @phpstan-import-type TemplateDefinitionShape from TemplateDefinition
 */
class TemplateBuilder
{
    private ?string $name = null;

    private ?string $category = null;

    private ?string $language = null;

    /** @var TemplateDefinition|TemplateDefinitionShape|null */
    private TemplateDefinition|array|null $definition = null;

    private ?bool $submitForReview = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
        private readonly ?Closure $onSaved = null,
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

    public function save(): APIResponseTemplate
    {
        if ($this->id !== null) {
            $result = $this->client->templates->update(
                id: $this->id,
                category: $this->category,
                definition: $this->definition,
                language: $this->language,
                name: $this->name,
                submitForReview: $this->submitForReview,
            );

            if ($this->onSaved !== null) {
                ($this->onSaved)();
            }

            return $result;
        }

        if ($this->name !== null) {
            throw new \InvalidArgumentException(
                'name() is not supported when creating a template — the Sent.dm API only accepts name on update. Create the template first, then use templates()->update() to set the name.'
            );
        }

        return $this->client->templates->create(
            category: $this->category,
            definition: $this->definition,
            language: $this->language,
            submitForReview: $this->submitForReview,
        );
    }
}
