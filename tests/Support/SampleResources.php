<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\tests\Support;

use tropikal\connect\n2n\application\ResourceApi;
use tropikal\connect\n2n\domain\resource\FieldSpec;
use tropikal\connect\n2n\domain\resource\ResourceSpec;
use tropikal\connect\n2n\domain\service\CapabilityFactory;
use tropikal\connect\n2n\domain\service\FieldProjection;
use tropikal\connect\n2n\infrastructure\discovery\StaticResourceCatalog;

/**
 * The Category + Article resource model used across the connect-core tests,
 * mirroring the canary's blog\bo\Category and blog\bo\Article. Article carries a
 * writable categoryId scalar — the connect representation of the ORM relation.
 */
final class SampleResources
{
    public static function category(): ResourceSpec
    {
        return new ResourceSpec('category', 'Categories', [
            new FieldSpec('name', 'string', readable: true, writable: true, required: true),
        ], searchable: ['name']);
    }

    public static function article(): ResourceSpec
    {
        return new ResourceSpec('article', 'Articles', [
            new FieldSpec('title', 'string', readable: true, writable: true, required: true),
            new FieldSpec('lead', 'string', readable: true, writable: true),
            new FieldSpec('online', 'boolean', readable: true, writable: true),
            new FieldSpec('categoryId', 'integer', readable: true, writable: true),
        ], searchable: ['title']);
    }

    public static function catalog(): StaticResourceCatalog
    {
        return new StaticResourceCatalog(self::category(), self::article());
    }

    /** @return array{0: ResourceApi, 1: InMemoryResourceStore} */
    public static function api(): array
    {
        $store = new InMemoryResourceStore;
        $api = new ResourceApi(
            self::catalog(),
            $store,
            new FieldProjection,
            new CapabilityFactory('n2n'),
            new NullAuditRecorder,
        );

        return [$api, $store];
    }
}
