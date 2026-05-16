<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\core\service\RocketEntityDeleter;
use tropikal\connect\n2n\core\service\RocketEntityReader;
use tropikal\connect\n2n\core\service\RocketEntitySearcher;
use tropikal\connect\n2n\core\service\RocketEntityWriter;
use tropikal\connect\n2n\dto\EntityDescriptor;

final class InMemoryRocketEntityAdapter implements RocketEntityDeleter, RocketEntityReader, RocketEntitySearcher, RocketEntityWriter
{
    /** @param array<string, array<string, array<string, mixed>>> $records */
    public function __construct(private array $records = []) {}

    public function list(EntityDescriptor $entity, array $payload): array
    {
        return array_values($this->records[$entity->key] ?? []);
    }

    public function get(EntityDescriptor $entity, string $id): ?array
    {
        return $this->records[$entity->key][$id] ?? null;
    }

    public function create(EntityDescriptor $entity, array $payload): array
    {
        $id = (string) (count($this->records[$entity->key] ?? []) + 1);
        $record = ['id' => $id, ...$payload];
        $this->records[$entity->key][$id] = $record;

        return $record;
    }

    public function update(EntityDescriptor $entity, string $id, array $payload): array
    {
        $record = $this->records[$entity->key][$id] ?? ['id' => $id];
        $this->records[$entity->key][$id] = [...$record, ...$payload];

        return $this->records[$entity->key][$id];
    }

    public function delete(EntityDescriptor $entity, string $id): bool
    {
        unset($this->records[$entity->key][$id]);

        return true;
    }
}
