<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\infrastructure\orm\OrmSession;

/**
 * In-memory OrmSession for tests. Stores objects per class, assigns sequential
 * integer ids on persist, and honours equality criteria / ordering / paging
 * closely enough to exercise the adapter logic without an n2n runtime.
 */
final class FakeOrmSession implements OrmSession
{
    /** @var array<class-string, array<int, object>> */
    private array $store = [];

    /** @var array<class-string, int> */
    private array $sequence = [];

    public function seed(object $entity, int $id): void
    {
        $entity->setId($id);
        $this->store[$entity::class][$id] = $entity;
        $this->sequence[$entity::class] = max($this->sequence[$entity::class] ?? 0, $id);
    }

    public function findAll(string $className, array $criteria = [], array $order = [], ?int $limit = null, ?int $offset = null): array
    {
        $rows = array_values($this->store[$className] ?? []);
        usort($rows, static fn (object $a, object $b): int => $b->getId() <=> $a->getId());
        if ($offset !== null) {
            $rows = array_slice($rows, $offset);
        }
        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    public function count(string $className, array $criteria = []): int
    {
        return count($this->store[$className] ?? []);
    }

    public function find(string $className, int|string $id): ?object
    {
        return $this->store[$className][(int) $id] ?? null;
    }

    public function persist(object $entity): void
    {
        if ($entity->getId() === null) {
            $id = ($this->sequence[$entity::class] ?? 0) + 1;
            $entity->setId($id);
            $this->sequence[$entity::class] = $id;
        }
        $this->store[$entity::class][$entity->getId()] = $entity;
    }

    public function remove(object $entity): void
    {
        unset($this->store[$entity::class][$entity->getId()]);
    }

    public function flush(): void
    {
        // no-op: the in-memory store is already consistent
    }
}
