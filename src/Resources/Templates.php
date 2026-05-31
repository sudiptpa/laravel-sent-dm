<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Templates\APIResponseTemplate;
use SentDm\Templates\Template;
use SentDm\Templates\TemplateListResponse;

class Templates extends Resource
{
    private int $page = 1;

    private int $pageSize = 50;

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

    public function get(): TemplateListResponse
    {
        return $this->cached(
            "sent.templates.{$this->page}.{$this->pageSize}",
            fn () => $this->client->templates->list(page: $this->page, pageSize: $this->pageSize),
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
        $this->client->templates->delete(id: $id);
        $this->forget("sent.template.{$id}");
    }
}
