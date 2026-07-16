<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\resource;

/** Pagination / search / filter arguments for a resource list request. */
final readonly class ListQuery
{
    /** @param array<string, scalar> $filters equality filters (field => value) */
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public ?string $search = null,
        public array $filters = [],
    ) {}

    public function offset(): int
    {
        return max(0, ($this->page - 1) * $this->perPage);
    }
}
