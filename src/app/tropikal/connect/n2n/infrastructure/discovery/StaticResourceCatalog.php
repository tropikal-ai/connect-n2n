<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\infrastructure\discovery;

use tropikal\connect\n2n\domain\port\ResourceCatalog;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/**
 * A catalog for a small, fixed set of resources declared in code — the minimal
 * plain-n2n path (e.g. Category + Article). No reflection or CMF spec walking.
 */
final readonly class StaticResourceCatalog implements ResourceCatalog
{
    /** @var array<string, ResourceSpec> */
    private array $resources;

    public function __construct(ResourceSpec ...$resources)
    {
        $keyed = [];
        foreach ($resources as $resource) {
            $keyed[$resource->slug] = $resource;
        }
        ksort($keyed);
        $this->resources = $keyed;
    }

    /** @return array<string, ResourceSpec> */
    public function all(): array
    {
        return $this->resources;
    }

    public function get(string $slug): ?ResourceSpec
    {
        return $this->resources[$slug] ?? null;
    }
}
