<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\core\service;

use tropikal\connect\n2n\dto\EntityDescriptor;

final class EntityFieldPolicy
{
    public function project(EntityDescriptor $entity, array $record): array
    {
        $projected = [];
        if (array_key_exists('id', $record)) {
            $projected['id'] = $record['id'];
        }

        foreach ($entity->fields as $field) {
            if ($field->readable && array_key_exists($field->key, $record)) {
                $projected[$field->key] = $record[$field->key];
            }
        }

        PublicPayloadGuard::assertPublicPayload($projected);

        return $projected;
    }

    public function validateWrite(EntityDescriptor $entity, array $payload, bool $requireRequiredFields): array
    {
        $allowed = [];
        $required = [];
        foreach ($entity->fields as $field) {
            if ($field->writable) {
                $allowed[] = $field->key;
                if ($field->required) {
                    $required[] = $field->key;
                }
            }
        }

        $unknown = array_values(array_diff(array_keys($payload), $allowed));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown fields: '.implode(', ', $unknown));
        }

        if ($requireRequiredFields) {
            $missing = array_values(array_diff($required, array_keys($payload)));
            if ($missing !== []) {
                throw new \InvalidArgumentException('Missing required fields: '.implode(', ', $missing));
            }
        }

        PublicPayloadGuard::assertPublicPayload($payload);

        return array_intersect_key($payload, array_flip($allowed));
    }
}
