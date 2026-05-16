<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\dto\EntityDescriptor;

interface RocketEntityWriter
{
    public function create(EntityDescriptor $entity, array $payload): array;

    public function update(EntityDescriptor $entity, string $id, array $payload): array;
}
