<?php

declare(strict_types=1);

namespace Sujip\SentDm\Concerns;

trait Paginatable
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
}
