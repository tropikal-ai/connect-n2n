<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\dto;

use tropikal\connect\n2n\core\service\PublicPayloadGuard;

final readonly class EntityManifest
{
    /** @param array<string, EntityDescriptor> $entities */
    public function __construct(
        public SiteIdentity $site,
        public array $entities,
    ) {}

    public function granted(array $grants): array
    {
        $entities = [];
        foreach ($this->entities as $key => $entity) {
            $entityGrants = $grants[$key] ?? [];
            if (! is_array($entityGrants)) {
                continue;
            }

            $operations = $this->operationsFor($entity, $entityGrants);
            if ($operations === []) {
                continue;
            }

            $entities[] = [
                'entity_key' => $entity->key,
                'label' => $entity->label,
                'access' => [
                    'read' => in_array('read', $entityGrants, true),
                    'write' => in_array('write', $entityGrants, true),
                    'delete' => in_array('delete', $entityGrants, true),
                ],
                'operations' => $operations,
                'input_schema' => $this->inputSchema($entity),
                'output_schema' => $this->outputSchema($entity),
            ];
        }

        $payload = [
            'integration' => 'n2n-rocket',
            'site' => $this->site->toArray(),
            'entities' => $entities,
        ];
        PublicPayloadGuard::assertPublicPayload($payload);

        return $payload;
    }

    private function operationsFor(EntityDescriptor $entity, array $grants): array
    {
        $operations = [];
        if (in_array('read', $grants, true)) {
            $operations = [...$operations, ...array_intersect(['list', 'get'], $entity->operations)];
        }
        if (in_array('write', $grants, true)) {
            $operations = [...$operations, ...array_intersect(['create', 'update'], $entity->operations)];
        }
        if (in_array('delete', $grants, true) && $entity->supports('delete')) {
            $operations[] = 'delete';
        }

        return array_values(array_unique($operations));
    }

    private function inputSchema(EntityDescriptor $entity): array
    {
        $properties = [];
        $required = [];
        foreach ($entity->fields as $field) {
            if (! $field->writable) {
                continue;
            }
            $properties[$field->key] = ['type' => $this->jsonType($field->type), 'title' => $field->label];
            if ($field->required) {
                $required[] = $field->key;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    private function outputSchema(EntityDescriptor $entity): array
    {
        $properties = ['id' => ['type' => 'string', 'title' => 'ID']];
        foreach ($entity->fields as $field) {
            if ($field->readable) {
                $properties[$field->key] = ['type' => $this->jsonType($field->type), 'title' => $field->label];
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];
    }

    private function jsonType(string $type): string
    {
        return match ($type) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'number', 'decimal' => 'number',
            'array', 'json' => 'array',
            default => 'string',
        };
    }
}
