<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\orm;

use tropikal\connect\n2n\domain\port\ResourceStore;
use tropikal\connect\n2n\domain\resource\ListQuery;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/**
 * ResourceStore backed by plain n2n ORM entities via the {@see OrmSession} port.
 * Maps resource records (field-keyed arrays) to entity getters/setters guided by
 * the ResourceSpec, resolving to-one relations declared in the OrmResourceBinding.
 * The single place connect-n2n touches n2n persistence is the injected session.
 */
final readonly class N2nResourceStore implements ResourceStore
{
    /** @param array<string, OrmResourceBinding> $bindings keyed by resource slug */
    public function __construct(
        private OrmSession $session,
        private array $bindings,
    ) {}

    public function list(ResourceSpec $resource, ListQuery $query): array
    {
        $binding = $this->binding($resource);
        $objects = $this->session->findAll(
            $binding->className,
            [],
            [$resource->sortColumn => 'DESC'],
            max(1, $query->perPage),
            $query->offset(),
        );

        return [
            'records' => array_map(fn (object $object): array => $this->toRecord($resource, $binding, $object), $objects),
            'total' => $this->session->count($binding->className),
        ];
    }

    public function get(ResourceSpec $resource, string $id): ?array
    {
        $binding = $this->binding($resource);
        $object = $this->session->find($binding->className, $this->castId($id));

        return $object !== null ? $this->toRecord($resource, $binding, $object) : null;
    }

    public function create(ResourceSpec $resource, array $data): array
    {
        $binding = $this->binding($resource);
        $class = $binding->className;
        $object = new $class;

        $this->applyWrite($binding, $object, $data);
        $this->session->persist($object);
        $this->session->flush();

        return $this->toRecord($resource, $binding, $object);
    }

    public function update(ResourceSpec $resource, string $id, array $data): array
    {
        $binding = $this->binding($resource);
        $object = $this->session->find($binding->className, $this->castId($id));
        if ($object === null) {
            throw new \InvalidArgumentException('Record not found.');
        }

        $this->applyWrite($binding, $object, $data);
        $this->session->flush();

        return $this->toRecord($resource, $binding, $object);
    }

    public function delete(ResourceSpec $resource, string $id): bool
    {
        $binding = $this->binding($resource);
        $object = $this->session->find($binding->className, $this->castId($id));
        if ($object === null) {
            return false;
        }

        $this->session->remove($object);
        $this->session->flush();

        return true;
    }

    private function binding(ResourceSpec $resource): OrmResourceBinding
    {
        return $this->bindings[$resource->slug]
            ?? throw new \InvalidArgumentException('No ORM binding for resource: '.$resource->slug);
    }

    /** @return array<string, mixed> */
    private function toRecord(ResourceSpec $resource, OrmResourceBinding $binding, object $object): array
    {
        $record = [$resource->identifier => $this->readScalar($object, $resource->identifier)];

        foreach ($resource->readableFieldNames() as $field) {
            $record[$field] = $binding->isRelation($field)
                ? $this->readRelationId($object, $binding->relationAccessorBase($field))
                : $this->readScalar($object, $field);
        }

        return $record;
    }

    /** @param array<string, mixed> $data */
    private function applyWrite(OrmResourceBinding $binding, object $object, array $data): void
    {
        foreach ($data as $field => $value) {
            if ($binding->isRelation($field)) {
                $this->writeRelation($object, $binding->relationAccessorBase($field), $binding->relations[$field], $value);

                continue;
            }
            $this->writeScalar($object, $field, $value);
        }
    }

    private function readScalar(object $object, string $field): mixed
    {
        foreach (['get'.ucfirst($field), 'is'.ucfirst($field)] as $getter) {
            if (method_exists($object, $getter)) {
                return $object->{$getter}();
            }
        }

        throw new \InvalidArgumentException('No getter for field: '.$field);
    }

    private function writeScalar(object $object, string $field, mixed $value): void
    {
        $setter = 'set'.ucfirst($field);
        if (! method_exists($object, $setter)) {
            throw new \InvalidArgumentException('No setter for field: '.$field);
        }
        $object->{$setter}($value);
    }

    private function readRelationId(object $object, string $accessorBase): int|string|null
    {
        $getter = 'get'.$accessorBase;
        if (! method_exists($object, $getter)) {
            return null;
        }
        $related = $object->{$getter}();
        if (! is_object($related) || ! method_exists($related, 'getId')) {
            return null;
        }
        $id = $related->getId();

        return is_int($id) || is_string($id) ? $id : null;
    }

    /** @param class-string $relatedClass */
    private function writeRelation(object $object, string $accessorBase, string $relatedClass, mixed $value): void
    {
        $setter = 'set'.$accessorBase;
        if (! method_exists($object, $setter)) {
            throw new \InvalidArgumentException('No setter for relation: '.$accessorBase);
        }

        if ($value === null || $value === '') {
            $object->{$setter}(null);

            return;
        }

        $related = $this->session->find($relatedClass, $this->castId((string) $value));
        if ($related === null) {
            throw new \InvalidArgumentException('Related record not found for '.$accessorBase.': '.$value);
        }
        $object->{$setter}($related);
    }

    private function castId(string $id): int|string
    {
        return ctype_digit($id) ? (int) $id : $id;
    }
}
