<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\port;

use tropikal\connect\n2n\domain\resource\ResourceSpec;

/** Source of the resources this site publishes to TROPIKAL Connect. */
interface ResourceCatalog
{
    /** @return array<string, ResourceSpec> keyed by slug */
    public function all(): array;

    public function get(string $slug): ?ResourceSpec;
}
