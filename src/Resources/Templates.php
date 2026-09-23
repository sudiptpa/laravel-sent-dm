<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Templates\APIResponseTemplate;
use SentDm\Templates\Template;
use SentDm\TemplatesPage;
use Sujip\SentDm\Builders\TemplateBuilder;
use Sujip\SentDm\Concerns\Paginatable;
use Sujip\SentDm\Support\Sandbox;

class Templates extends Resource
{
    use Paginatable;

    private ?string $category = null;

    private ?string $status = null;

    private ?string $search = null;

    private ?bool $isWelcomePlayground = null;

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

    /**
     * Pages retain a live SDK client for pagination and must not be cached.
     *
     * @return TemplatesPage<Template>
     */
    public function get(): TemplatesPage
    {
        return $this->client->templates->list(
            page: $this->page,
            pageSize: $this->pageSize,
            category: $this->category,
            status: $this->status,
            search: $this->search,
            isWelcomePlayground: $this->isWelcomePlayground,
            xProfileID: $this->orgProfileId,
        );
    }

    public function create(): TemplateBuilder
    {
        return new TemplateBuilder(
            client: $this->client,
            profileId: $this->orgProfileId,
            sandboxDefault: $this->sandbox,
            onSaved: fn () => $this->forget('sent.templates.name-version'),
        );
    }

    public function update(string $id): TemplateBuilder
    {
        return new TemplateBuilder(
            client: $this->client,
            id: $id,
            profileId: $this->orgProfileId,
            sandboxDefault: $this->sandbox,
            onSaved: function () use ($id): void {
                $this->forget("sent.template.{$id}");
                $this->forget('sent.templates.name-version');
            },
        );
    }

    public function find(string $id): APIResponseTemplate
    {
        return $this->cached(
            "sent.template.{$id}",
            fn () => $this->client->templates->retrieve(id: $id, xProfileID: $this->orgProfileId),
        );
    }

    public function findByName(string $name): ?Template
    {
        return $this->cached(
            "sent.template.name.{$this->nameCacheVersion()}.{$name}",
            function () use ($name): ?Template {
                $response = $this->client->templates->list(
                    page: 1,
                    pageSize: 100,
                    search: $name,
                    xProfileID: $this->orgProfileId,
                );

                foreach ($response->data->templates ?? [] as $template) {
                    if ($template instanceof Template && $template->name === $name) {
                        return $template;
                    }
                }

                return null;
            },
        );
    }

    public function delete(string $id, ?bool $sandbox = null, ?bool $deleteFromMeta = null): void
    {
        $this->client->templates->delete(
            id: $id,
            deleteFromMeta: $deleteFromMeta,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            xProfileID: $this->orgProfileId,
        );
        $this->forget("sent.template.{$id}");
        $this->forget('sent.templates.name-version');
    }

    private function nameCacheVersion(): string
    {
        $version = $this->readCached('sent.templates.name-version');

        if (is_string($version)) {
            return $version;
        }

        return $this->cached('sent.templates.name-version', fn () => bin2hex(random_bytes(16)));
    }
}
