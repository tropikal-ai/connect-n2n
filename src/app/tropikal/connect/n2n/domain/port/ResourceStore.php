<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/**
 * Persistence port for a connectable resource. Records are plain associative
 * arrays keyed by the resource's field names (plus its identifier). Concrete
 * adapters live in infrastructure (n2n ORM, in-memory for tests).
 */
interface ResourceStore
{
    /**
     * @return array{records: array<int, array<string, mixed>>, total: int}
     */
    public function list(ResourceSpec $resource, ListQuery $query): array;

    /** @return array<string, mixed>|null */
    public function get(ResourceSpec $resource, string $id): ?array;

    /**
     * @param  array<string, mixed>  $data  already validated to writable fields
     * @return array<string, mixed>
     */
    public function create(ResourceSpec $resource, array $data): array;

    /**
     * @param  array<string, mixed>  $data  already validated to writable fields
     * @return array<string, mixed>
     */
    public function update(ResourceSpec $resource, string $id, array $data): array;

    public function delete(ResourceSpec $resource, string $id): bool;
}
