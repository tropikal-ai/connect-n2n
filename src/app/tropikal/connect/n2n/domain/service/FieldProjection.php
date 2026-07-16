<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\service;

use tropikal\connect\n2n\domain\exception\InvalidWriteException;
use tropikal\connect\n2n\domain\resource\ResourceSpec;

/**
 * The two-way field gate. project() exposes only the identifier plus readable
 * fields; validateWrite() accepts only writable fields, rejecting unknown ones
 * and (on create) enforcing required fields. Nothing outside the declared
 * contract can be read or written.
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the payload filtered to writable fields
     *
     * @throws InvalidWriteException
     */
    public function validateWrite(ResourceSpec $resource, array $data, bool $creating): array
    {
        $writable = $resource->writableFieldNames();

        $unknown = array_values(array_diff(array_keys($data), $writable));
        if ($unknown !== []) {
            throw new InvalidWriteException('unknown_fields', 'Unknown fields: '.implode(', ', $unknown), $unknown);
        }

        if ($creating) {
            $missing = array_values(array_diff($resource->requiredFieldNames(), array_keys($data)));
            if ($missing !== []) {
                throw new InvalidWriteException('missing_required', 'Missing required fields: '.implode(', ', $missing), $missing);
            }
        }

        return array_intersect_key($data, array_flip($writable));
    }
}
