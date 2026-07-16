<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\service;

use tropikal\connect\n2n\application\ResourceWriteBinder;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/**
 * The read-side field gate: project() exposes only the identifier plus
 * readable fields. The write side is enforced by
 * {@see ResourceWriteBinder}.
 */
final class FieldProjection
{
    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function project(ResourceSpec $resource, array $record): array
    {
        $projected = [];
        if (array_key_exists($resource->identifier, $record)) {
            $projected[$resource->identifier] = $record[$resource->identifier];
        }
        foreach ($resource->readableFieldNames() as $name) {
            if (array_key_exists($name, $record)) {
                $projected[$name] = $record[$name];
            }
        }

        return $projected;
    }
}
