<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Templates\TemplateGetResponse;
use SentDm\Templates\TemplateListResponse;
use SentDm\Templates\TemplateListResponse\Data\Template;
use Sujip\SentDm\Builders\TemplateBuilder;

class Templates extends Resource
{
    private int $page = 1;

    private int $pageSize = 50;

    private ?string $category = null;

    private ?string $status = null;

    private ?string $search = null;

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

    public function search(string $search): static
    {
        $clone = clone $this;
        $clone->search = $search;

        return $clone;
    }

    /**
     * @deprecated Sent.dm's August 2026 platform changelog: this filter was removed from
     * `GET /v3/templates` server-side. The parameter is still accepted and ignored, so
     * calling this no longer narrows results, it's a silent no-op. Kept only because the
     * SDK still declares the param; drop this method once the SDK does.
     */
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
            'sea' => $this->search ?? '__null__',
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
                search: $this->search,
                isWelcomePlayground: $this->isWelcomePlayground,
                xProfileID: $this->orgProfileId,
            ),
        );
    }

    public function create(): TemplateBuilder
    {
        return new TemplateBuilder(client: $this->client, profileId: $this->orgProfileId, sandboxDefault: $this->sandbox);
    }

    public function update(string $id): TemplateBuilder
    {
        return new TemplateBuilder(
            client: $this->client,
            id: $id,
            profileId: $this->orgProfileId,
            sandboxDefault: $this->sandbox,
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

    public function find(string $id): TemplateGetResponse
    {
        return $this->cached(
            "sent.template.{$id}",
            fn () => $this->client->templates->retrieve(id: $id, xProfileID: $this->orgProfileId),
        );
    }

    public function findByName(string $name): ?Template
    {
        return $this->cached(
            "sent.template.name.{$name}",
            function () use ($name): ?Template {
                $response = $this->client->templates->list(
                    page: 1,
                    pageSize: 100,
                    search: $name,
                    xProfileID: $this->orgProfileId,
                );

                foreach ($response->data->templates ?? [] as $template) {
                    if ($template->name === $name) {
                        return $template;
                    }
                }

                return null;
            },
        );
    }

    public function delete(string $id, ?bool $sandbox = null, ?bool $deleteFromMeta = null): void
    {
        $name = $this->cachedTemplateName($id);
        $this->client->templates->delete(
            id: $id,
            deleteFromMeta: $deleteFromMeta,
            sandbox: ($sandbox ?? $this->sandbox) ?: null,
            xProfileID: $this->orgProfileId,
        );
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
