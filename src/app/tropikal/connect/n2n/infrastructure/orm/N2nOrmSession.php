<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\orm;

use n2n\persistence\orm\EntityManager;

/**
 * The single seam that touches the n2n EntityManager. Everything else in
 * connect-n2n depends on the {@see OrmSession} port, keeping the domain and
 * application layers free of the persistence framework.
 *
 * Pagination is applied in PHP after fetch: correct and simple for the small,
 * bounded resources a connect site exposes. Swap in criteria->limit() here if a
 * resource ever grows large.
 */
final readonly class N2nOrmSession implements OrmSession
{
    public function __construct(private EntityManager $em) {}

    public function findAll(string $className, array $criteria = [], array $order = [], ?int $limit = null, ?int $offset = null): array
    {
        $rows = $this->em
            ->createSimpleCriteria($className, $criteria !== [] ? $criteria : null, $order !== [] ? $order : null)
            ->toQuery()
            ->fetchArray();

        $rows = array_values($rows);
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
        return count($this->em
            ->createSimpleCriteria($className, $criteria !== [] ? $criteria : null)
            ->toQuery()
            ->fetchArray());
    }

    public function find(string $className, int|string $id): ?object
    {
        $entity = $this->em->find($className, $id);

        return is_object($entity) ? $entity : null;
    }

    public function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->em->remove($entity);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
