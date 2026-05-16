<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\dto\EntityDescriptor;

final readonly class EntityDiscoveryService
{
    public function __construct(
        private \Closure $rocketResolver,
        private RocketSchemaMapper $mapper,
    ) {}

    /** @return array<string, EntityDescriptor> */
    public function discover(): array
    {
        $rocket = ($this->rocketResolver)();
        if (! is_object($rocket) || ! method_exists($rocket, 'getSpec')) {
            return [];
        }

        $spec = $rocket->getSpec();
        if (! is_object($spec)) {
            return [];
        }

        return $this->mapper->mapSpec($spec);
    }
}
