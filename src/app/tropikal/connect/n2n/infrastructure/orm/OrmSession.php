<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\orm;

/**
 * Narrow persistence port used by {@see OrmEntityAdapter}. It deliberately
 * exposes only the handful of operations the bridge needs, so the generic
 * adapter stays framework-neutral and unit-testable. The n2n binding
 * ({@see N2nOrmSession}) is the single place that touches the n2n EntityManager.
 */
interface OrmSession
{
    /**
     * @param  class-string  $className
     * @param  array<string, mixed>  $criteria  equality filters (field => value)
     * @param  array<string, string>  $order  field => 'ASC'|'DESC'
     * @return array<int, object>
     */
    public function findAll(string $className, array $criteria = [], array $order = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * @param  class-string  $className
     * @param  array<string, mixed>  $criteria
     */
    public function count(string $className, array $criteria = []): int;

    /** @param class-string $className */
    public function find(string $className, int|string $id): ?object;

    public function persist(object $entity): void;

    public function remove(object $entity): void;

    public function flush(): void;
}
