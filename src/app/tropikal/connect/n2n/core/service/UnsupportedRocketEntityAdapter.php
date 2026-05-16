<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\dto\EntityDescriptor;
use tropikal\connect\n2n\exception\UnsupportedRocketOperationException;

final class UnsupportedRocketEntityAdapter implements RocketEntityDeleter, RocketEntityReader, RocketEntitySearcher, RocketEntityWriter
{
    public function list(EntityDescriptor $entity, array $payload): array
    {
        throw new UnsupportedRocketOperationException('Rocket entity listing is not wired for this application.');
    }

    public function get(EntityDescriptor $entity, string $id): ?array
    {
        throw new UnsupportedRocketOperationException('Rocket entity reading is not wired for this application.');
    }

    public function create(EntityDescriptor $entity, array $payload): array
    {
        throw new UnsupportedRocketOperationException('Rocket entity creation is not wired for this application.');
    }

    public function update(EntityDescriptor $entity, string $id, array $payload): array
    {
        throw new UnsupportedRocketOperationException('Rocket entity update is not wired for this application.');
    }

    public function delete(EntityDescriptor $entity, string $id): bool
    {
        throw new UnsupportedRocketOperationException('Rocket entity deletion is not wired for this application.');
    }
}
