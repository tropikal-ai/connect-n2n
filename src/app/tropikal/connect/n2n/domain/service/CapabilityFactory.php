<?php

declare(strict_types=1);

namespace tropikal\connect\n2n\domain\service;

use tropikal\connect\n2n\domain\resource\Operation;
use tropikal\connect\n2n\domain\resource\ResourceSpec;
use TropikalAI\Connect\Domain\Capabilities\CapabilityDescriptor;
use TropikalAI\Connect\Domain\Capabilities\FieldDescriptor;
use TropikalAI\Connect\Domain\Capabilities\OperationDescriptor;

/**
 * Builds the contract-faithful capability descriptor for a resource, using the
 * shared tropikal-ai/connect core lib. Which operations are advertised follows
 * the granted permissions (read => list+get, create&update => create+update,
 * delete => delete), each with its risk level and JSON input/output schema —
 * the same shape the Filament adapter emits.
 */
final readonly class CapabilityFactory
{
    public function __construct(private string $sourceKind = 'n2n') {}

    /** @param array<int, string> $permissions granted permission values for this resource */
    public function forResource(ResourceSpec $resource, array $permissions): CapabilityDescriptor
    {
        $fields = [];
        foreach ($resource->fields as $name => $spec) {
            $fields[$name] = new FieldDescriptor(
                name: $name,
                type: $spec->type,
                readable: $spec->readable,
                writable: $spec->writable,
                required: $spec->required,
            );
        }

        $operations = [];
        if (in_array('read', $permissions, true)) {
            $operations[] = $this->operation($resource, Operation::List);
            $operations[] = $this->operation($resource, Operation::Get);
        }
        if (in_array('create', $permissions, true) && in_array('update', $permissions, true)) {
            $operations[] = $this->operation($resource, Operation::Create);
            $operations[] = $this->operation($resource, Operation::Update);
        }
        if (in_array('delete', $permissions, true)) {
            $operations[] = $this->operation($resource, Operation::Delete);
        }

        return new CapabilityDescriptor(
            sourceKind: $this->sourceKind,
            resourceKey: $resource->slug,
            resourceLabel: $resource->label,
            identifier: $resource->identifier,
            fields: $fields,
            operations: $operations,
            grants: array_values($permissions),
        );
    }

    private function operation(ResourceSpec $resource, Operation $operation): OperationDescriptor
    {
        return new OperationDescriptor(
            name: $resource->slug.'.'.$operation->value,
            operation: $operation->value,
            riskLevel: $operation->riskLevel(),
            inputSchema: $this->inputSchema($resource, $operation),
            outputSchema: $this->outputSchema($operation),
            requiresConfirmation: $operation->requiresConfirmation(),
        );
    }

    /** @return array<string, mixed> */
    private function inputSchema(ResourceSpec $resource, Operation $operation): array
    {
        return match ($operation) {
            Operation::List => $this->listInputSchema($resource),
            Operation::Get, Operation::Delete => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id'],
                'properties' => ['id' => ['type' => 'string']],
            ],
            Operation::Create => $this->writeInputSchema($resource, true),
            Operation::Update => $this->withId($this->writeInputSchema($resource, false)),
        };
    }

    /** @return array<string, mixed> */
    private function outputSchema(Operation $operation): array
    {
        return match ($operation) {
            Operation::List => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array'],
                    'meta' => ['type' => 'object'],
                ],
            ],
            Operation::Delete => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'deleted' => ['type' => 'boolean'],
                ],
            ],
            default => ['type' => 'object'],
        };
    }

    /** @return array<string, mixed> */
    private function listInputSchema(ResourceSpec $resource): array
    {
        $properties = [
            'page' => ['type' => 'integer', 'minimum' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1],
        ];
        if ($resource->searchable !== []) {
            $properties['search'] = ['type' => 'string'];
        }

        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties];
    }

    /** @return array<string, mixed> */
    private function writeInputSchema(ResourceSpec $resource, bool $creating): array
    {
        $properties = [];
        $required = [];
        foreach ($resource->fields as $name => $spec) {
            if (! $spec->writable) {
                continue;
            }
            $properties[$name] = $this->jsonType($spec->type);
            if ($creating && $spec->required) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function withId(array $schema): array
    {
        $schema['properties'] = ['id' => ['type' => 'string'], ...($schema['properties'] ?? [])];
        $schema['required'] = ['id'];

        return $schema;
    }

    /** @return array<string, mixed> */
    private function jsonType(string $type): array
    {
        return match ($type) {
            'integer' => ['type' => 'integer'],
            'boolean' => ['type' => 'boolean'],
            'json' => ['type' => 'object'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            default => ['type' => 'string'],
        };
    }
}
