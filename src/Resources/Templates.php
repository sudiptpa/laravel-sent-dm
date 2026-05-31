<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Templates\APIResponseTemplate;
use SentDm\Templates\Template;
use SentDm\Templates\TemplateListResponse;
use Sujip\SentDm\Builders\TemplateBuilder;

class Templates extends Resource
{
    private int $page = 1;

    private int $pageSize = 50;

    private ?string $category = null;

    private ?string $status = null;

    private ?bool $isWelcomePlayground = null;

    public function page(int $page): static
    {
        $clone = clone $this;
        $clone->page = $page;

        return $clone;
    }

    public function perPage(int $perPage): static
    {
        $clone = clone $this;
        $clone->pageSize = $perPage;

        return $clone;
    }

    public function category(string $category): static
    {
        $clone = clone $this;
        $clone->category = $category;

        return $clone;
    }

    public function status(string $status): static
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function isWelcomePlayground(bool $value = true): static
    {
        $clone = clone $this;
        $clone->isWelcomePlayground = $value;

        return $clone;
    }

    public function get(): TemplateListResponse
    {
        $cacheKey = 'sent.templates.'.http_build_query([
            'page' => $this->page,
            'size' => $this->pageSize,
            'cat' => $this->category ?? '__null__',
            'sta' => $this->status ?? '__null__',
            'wlpg' => match ($this->isWelcomePlayground) {
                null => '__null__',
                true => '1',
                false => '0',
            },
        ]);

        return $this->cached(
            $cacheKey,
            fn () => $this->client->templates->list(
                page: $this->page,
                pageSize: $this->pageSize,
                category: $this->category,
                status: $this->status,
                isWelcomePlayground: $this->isWelcomePlayground,
            ),
        );
    }

    public function create(): TemplateBuilder
    {
        return new TemplateBuilder(client: $this->client);
    }

    public function update(string $id): TemplateBuilder
    {
        return new TemplateBuilder(
            client: $this->client,
            id: $id,
            onSaved: function () use ($id): void {
                // Read the old name from cache before evicting the find entry
                // so we can also clear its findByName slot.
                $name = $this->cachedTemplateName($id);
                $this->forget("sent.template.{$id}");
                if ($name !== null) {
                    $this->forget("sent.template.name.{$name}");
                }
            },
        );
    }

    public function find(string $id): APIResponseTemplate
    {
        return $this->cached(
            "sent.template.{$id}",
            fn () => $this->client->templates->retrieve(id: $id),
        );
    }

    public function findByName(string $name): ?Template
    {
        return $this->cached(
            "sent.template.name.{$name}",
            function () use ($name): ?Template {
                $response = $this->client->templates->list(page: 1, pageSize: 100, search: $name);

                foreach ($response->data->templates ?? [] as $template) {
                    if ($template->name === $name) {
                        return $template;
                    }
                }

                return null;
            },
        );
    }

    public function delete(string $id): void
    {
        $name = $this->cachedTemplateName($id);
        $this->client->templates->delete(id: $id);
        $this->forget("sent.template.{$id}");
        if ($name !== null) {
            $this->forget("sent.template.name.{$name}");
        }
    }

    private function cachedTemplateName(string $id): ?string
    {
        $cached = $this->readCached("sent.template.{$id}");

        if (! is_object($cached)) {
            return null;
        }

        $data = $cached->data ?? null;
        $name = is_object($data) ? ($data->name ?? null) : null;

        return is_string($name) ? $name : null;
    }
}
