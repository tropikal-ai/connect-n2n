<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\domain\port\ResourceStore;
use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/** In-memory ResourceStore for the connect-core tests (no n2n runtime). */
final class InMemoryResourceStore implements ResourceStore
{
    /** @var array<string, array<int|string, array<string, mixed>>> */
    private array $rows = [];

    /** @var array<string, int> */
    private array $sequence = [];

    /** @param array<string, mixed> $record */
    public function seed(string $slug, array $record): void
    {
        $id = $record['id'];
        $this->rows[$slug][$id] = $record;
        if (is_int($id)) {
            $this->sequence[$slug] = max($this->sequence[$slug] ?? 0, $id);
        }
    }

    public function list(ResourceSpec $resource, ListQuery $query): array
    {
        $records = array_values($this->rows[$resource->slug] ?? []);
        usort($records, static fn (array $a, array $b): int => ($b['id'] <=> $a['id']));
        $total = count($records);

        return [
            'records' => array_slice($records, $query->offset(), max(1, $query->perPage)),
            'total' => $total,
        ];
    }

    public function get(ResourceSpec $resource, string $id): ?array
    {
        return $this->rows[$resource->slug][$this->key($id)] ?? null;
    }

    public function create(ResourceSpec $resource, array $data): array
    {
        $id = ($this->sequence[$resource->slug] ?? 0) + 1;
        $this->sequence[$resource->slug] = $id;
        $record = ['id' => $id, ...$data];
        $this->rows[$resource->slug][$id] = $record;

        return $record;
    }

    public function update(ResourceSpec $resource, string $id, array $data): array
    {
        $key = $this->key($id);
        $record = [...($this->rows[$resource->slug][$key] ?? ['id' => $key]), ...$data];
        $this->rows[$resource->slug][$key] = $record;

        return $record;
    }

    public function delete(ResourceSpec $resource, string $id): bool
    {
        $key = $this->key($id);
        if (! isset($this->rows[$resource->slug][$key])) {
            return false;
        }
        unset($this->rows[$resource->slug][$key]);

        return true;
    }

    private function key(string $id): int|string
    {
        return ctype_digit($id) ? (int) $id : $id;
    }
}
