<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\dto\EntityDescriptor;

interface RocketEntityReader
{
    public function get(EntityDescriptor $entity, string $id): ?array;
}
